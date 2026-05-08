<?php

namespace App\Services;

use App\Models\Import;
use App\Models\ProductMapping;
use App\Models\RequiredAction;
use Illuminate\Support\Arr;

class RequiredActionResolver
{
    public function __construct(private readonly MappingRuleMatcher $matcher) {}

    public function resolveForMapping(ProductMapping $mapping): int
    {
        $mapping->loadMissing(['rules', 'variant.productType']);
        $resolved = 0;

        RequiredAction::query()
            ->forTenant($mapping->tenant_id)
            ->whereIn('status', ['open', 'in_progress', 'escalated'])
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
                    'escalated_at' => null,
                    'last_activity_at' => now(),
                    'resolution_payload' => [
                        'resolved_by_mapping_id' => $mapping->id,
                        'product_variant_id' => $mapping->product_variant_id,
                    ],
                    'payload' => [
                        ...($action->payload ?? []),
                        'resolved_by_mapping_id' => $mapping->id,
                        'resolved_product_variant_id' => $mapping->product_variant_id,
                    ],
                ]);

                if ($action->order) {
                    $this->applyMappingToOrderItems($action, $mapping);
                }

                $this->applyMappingToImportRows($action, $mapping);

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
            ->whereIn('status', ['open', 'in_progress', 'escalated'])
            ->exists();

        if (! $hasOpenActions && $action->order->status === 'action_needed') {
            $action->order->update(['status' => 'verified']);
        }
    }

    private function applyMappingToImportRows(RequiredAction $action, ProductMapping $mapping): void
    {
        $payload = $action->payload ?? [];
        $orderNumber = Arr::get($payload, 'order_number');
        $itemSku = Arr::get($payload, 'item_sku');
        $itemName = Arr::get($payload, 'item_name');

        Import::query()
            ->forTenant($action->tenant_id)
            ->with('rows')
            ->get()
            ->each(function (Import $import) use ($orderNumber, $itemSku, $itemName, $mapping): void {
                foreach ($import->rows as $row) {
                    if ($row->status !== 'needs_action') {
                        continue;
                    }

                    $rowPayload = $row->payload ?? [];
                    $sameOrder = $orderNumber && Arr::get($rowPayload, 'order_number') === $orderNumber;
                    $sameSku = $itemSku && Arr::get($rowPayload, 'item_sku') === $itemSku;
                    $sameName = $itemName && Arr::get($rowPayload, 'item_name') === $itemName;

                    if (! $sameOrder && ! $sameSku && ! $sameName) {
                        continue;
                    }

                    $row->update([
                        'status' => 'ready',
                        'errors' => [],
                        'payload' => [
                            ...$rowPayload,
                            'matched_product_variant_id' => $mapping->product_variant_id,
                            'matched_mapping_id' => $mapping->id,
                            'resolved_by_required_action' => true,
                        ],
                    ]);
                }

                $import->update([
                    'valid_rows' => $import->rows()->where('status', 'ready')->count(),
                    'invalid_rows' => $import->rows()->where('status', 'needs_action')->count(),
                ]);
            });
    }
}
