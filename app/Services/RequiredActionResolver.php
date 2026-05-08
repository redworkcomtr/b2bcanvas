<?php

namespace App\Services;

use App\Models\ProductMapping;
use App\Models\RequiredAction;

class RequiredActionResolver
{
    public function __construct(private readonly MappingRuleMatcher $matcher) {}

    public function resolveForMapping(ProductMapping $mapping): int
    {
        $mapping->loadMissing(['rules', 'variant.productType']);
        $resolved = 0;

        RequiredAction::query()
            ->forTenant($mapping->tenant_id)
            ->where('status', 'open')
            ->where('type', 'product_mapping_required')
            ->with(['order.items'])
            ->get()
            ->each(function (RequiredAction $action) use ($mapping, &$resolved): void {
                $payload = [
                    'name' => $action->payload['item_name'] ?? $action->payload['name'] ?? null,
                    'sku' => $action->payload['item_sku'] ?? $action->payload['sku'] ?? null,
                    'fulfillment_sku' => $action->payload['fulfillment_sku'] ?? null,
                ];

                if (! $this->matcher->mappingMatches($payload, $mapping)) {
                    return;
                }

                $action->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'last_activity_at' => now(),
                    'payload' => [
                        ...($action->payload ?? []),
                        'resolved_by_mapping_id' => $mapping->id,
                        'resolved_product_variant_id' => $mapping->product_variant_id,
                    ],
                ]);

                if ($action->order) {
                    $this->applyMappingToOrderItems($action, $mapping);
                }

                $resolved++;
            });

        return $resolved;
    }

    private function applyMappingToOrderItems(RequiredAction $action, ProductMapping $mapping): void
    {
        $variant = $mapping->variant;
        $itemName = mb_strtolower((string) ($action->payload['item_name'] ?? ''));
        $itemSku = mb_strtolower((string) ($action->payload['item_sku'] ?? ''));

        foreach ($action->order->items as $item) {
            $nameMatches = $itemName !== '' && mb_strtolower($item->item_name) === $itemName;
            $skuMatches = $itemSku !== '' && mb_strtolower((string) $item->item_sku) === $itemSku;

            if (! $nameMatches && ! $skuMatches) {
                continue;
            }

            $item->update([
                'product_variant_id' => $variant->id,
                'product_code' => $variant->sku,
                'product_type' => $variant->productType?->name,
                'panel_summary' => implode(', ', $variant->panel_sizes ?? []),
            ]);
        }

        $hasOpenActions = $action->order->requiredActions()
            ->where('status', 'open')
            ->exists();

        if (! $hasOpenActions && $action->order->status === 'action_needed') {
            $action->order->update(['status' => 'verified']);
        }
    }
}
