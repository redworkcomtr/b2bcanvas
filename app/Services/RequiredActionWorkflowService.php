<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Import;
use App\Models\MediaFile;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\RequiredAction;
use App\Models\RequiredActionComment;
use App\Models\User;
use Illuminate\Support\Arr;

class RequiredActionWorkflowService
{
    /**
     * @param  array<string, mixed>  $resolution
     */
    public function resolve(RequiredAction $action, User $user, array $resolution = [], ?string $comment = null): RequiredAction
    {
        $this->applyResolution($action, $resolution);

        $payload = $action->payload ?? [];
        $action->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'escalated_at' => null,
            'resolution_payload' => $resolution,
            'payload' => [
                ...$payload,
                'resolution' => $resolution,
            ],
            'last_activity_at' => now(),
        ]);

        if ($comment) {
            $this->comment($action, $user, $comment);
        }

        $this->revalidateImportRows($action);
        $this->refreshOrderStatus($action);
        $this->audit($action, $user, 'required_action.resolved', ['resolution' => $resolution]);

        return $this->freshAction($action);
    }

    public function reopen(RequiredAction $action, User $user, ?string $comment = null): RequiredAction
    {
        $action->update([
            'status' => 'open',
            'resolved_at' => null,
            'last_activity_at' => now(),
        ]);

        if ($comment) {
            $this->comment($action, $user, $comment);
        }

        if ($action->order && $action->order->status !== 'cancelled') {
            $action->order->update(['status' => 'action_needed']);
        }

        $this->audit($action, $user, 'required_action.reopened', ['comment' => $comment]);

        return $this->freshAction($action);
    }

    public function escalate(RequiredAction $action, User $user, ?string $comment = null, string $priority = 'urgent'): RequiredAction
    {
        $action->update([
            'status' => 'escalated',
            'priority' => $priority,
            'assigned_to_id' => $user->id,
            'escalated_at' => now(),
            'last_activity_at' => now(),
        ]);

        if ($comment) {
            $this->comment($action, $user, $comment, true);
        }

        $this->audit($action, $user, 'required_action.escalated', [
            'priority' => $priority,
            'comment' => $comment,
        ]);

        return $this->freshAction($action);
    }

    public function comment(RequiredAction $action, User $user, string $body, bool $internal = false): RequiredActionComment
    {
        $comment = $action->comments()->create([
            'user_id' => $user->id,
            'body' => $body,
            'attachments' => [],
            'internal' => $internal,
        ]);

        $action->update(['last_activity_at' => now()]);

        $this->audit($action, $user, 'required_action.comment_added', [
            'comment_id' => $comment->id,
            'internal' => $internal,
        ]);

        return $comment->load('user');
    }

    /**
     * @param  array<string, mixed>  $resolution
     */
    private function applyResolution(RequiredAction $action, array $resolution): void
    {
        $action->loadMissing(['order.items.variant.productType']);

        if (in_array($action->type, ['product_mapping_required', 'product_unavailable'], true)) {
            $variantId = Arr::get($resolution, 'product_variant_id');
            if ($variantId) {
                $variant = ProductVariant::query()
                    ->whereHas('productType', fn ($query) => $query->forTenant($action->tenant_id))
                    ->with('productType')
                    ->find($variantId);

                if ($variant) {
                    $this->applyVariantToOrderItems($action, $variant);
                }
            }
        }

        if ($action->type === 'invalid_artwork') {
            $this->applyArtworkResolution($action, $resolution);
        }

        if ($action->type === 'duplicate_order') {
            $this->applyDuplicateResolution($action, $resolution);
        }

        if ($action->type === 'address_error' && $action->order) {
            $address = Arr::get($resolution, 'shipping_address');
            if (is_array($address) && $address !== []) {
                $action->order->update([
                    'shipping_address' => array_filter($address, fn ($value) => filled($value)),
                    'customer_name' => Arr::get($resolution, 'customer_name', $action->order->customer_name),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $resolution
     */
    private function applyArtworkResolution(RequiredAction $action, array $resolution): void
    {
        $mediaId = Arr::get($resolution, 'artwork_media_file_id')
            ?? Arr::get($resolution, 'replacement_media_file_id');

        if (! $mediaId || ! $action->order) {
            return;
        }

        $media = MediaFile::query()
            ->forTenant($action->tenant_id)
            ->whereKey($mediaId)
            ->first();

        if (! $media) {
            return;
        }

        $payload = $action->payload ?? [];
        $targetItemId = Arr::get($payload, 'order_item_id') ?? Arr::get($payload, 'item_id');
        $itemName = mb_strtolower((string) (Arr::get($payload, 'item_name') ?? Arr::get($payload, 'name') ?? ''));
        $itemSku = mb_strtolower((string) (Arr::get($payload, 'item_sku') ?? Arr::get($payload, 'sku') ?? ''));

        foreach ($action->order->items as $item) {
            $idMatches = $targetItemId && (int) $item->id === (int) $targetItemId;
            $nameMatches = $itemName !== '' && mb_strtolower($item->item_name) === $itemName;
            $skuMatches = $itemSku !== '' && mb_strtolower((string) $item->item_sku) === $itemSku;

            if (! $idMatches && ! $nameMatches && ! $skuMatches && $action->order->items->count() > 1) {
                continue;
            }

            $item->update([
                'design_images' => array_values(array_unique([
                    ...($item->design_images ?? []),
                    $media->url,
                ])),
                'options' => [
                    ...($item->options ?? []),
                    'artwork_media_file_id' => $media->id,
                    'artwork_resolved_at' => now()->toISOString(),
                ],
            ]);
        }

        $media->update([
            'mediable_type' => Order::class,
            'mediable_id' => $action->order->id,
            'metadata' => [
                ...($media->metadata ?? []),
                'resolved_required_action_id' => $action->id,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $resolution
     */
    private function applyDuplicateResolution(RequiredAction $action, array $resolution): void
    {
        $payload = $action->payload ?? [];
        $decision = (string) (Arr::get($resolution, 'decision') ?? 'skip');
        $replacementOrderNumber = trim((string) (Arr::get($resolution, 'replacement_order_number') ?? ''));
        $importId = Arr::get($payload, 'import_id');
        $rowNumber = Arr::get($payload, 'row_number');

        if ($decision === 'cancel_existing') {
            $this->cancelExistingDuplicateOrder($action);
        }

        if (! $importId || ! $rowNumber) {
            return;
        }

        $import = Import::query()
            ->forTenant($action->tenant_id)
            ->whereKey($importId)
            ->first();

        $row = $import?->rows()->where('row_number', $rowNumber)->first();
        if (! $row) {
            return;
        }

        $rowPayload = $row->payload ?? [];
        $errors = collect($row->errors ?? [])
            ->reject(fn ($error): bool => str_contains((string) $error, 'order_number already exists'))
            ->values()
            ->all();

        if ($decision === 'process_with_new_number' && $replacementOrderNumber !== '') {
            $replacementExists = Order::query()
                ->forTenant($action->tenant_id)
                ->where('order_number', $replacementOrderNumber)
                ->exists();

            $row->update([
                'status' => $errors === [] && ! $replacementExists ? 'ready' : 'needs_action',
                'errors' => $replacementExists ? [...$errors, 'replacement_order_number already exists.'] : $errors,
                'payload' => [
                    ...$rowPayload,
                    'order_number' => $replacementOrderNumber,
                    'original_order_number' => Arr::get($rowPayload, 'order_number'),
                    'replacement_order_number' => $replacementOrderNumber,
                    'duplicate_decision' => $decision,
                ],
            ]);
        } else {
            $row->update([
                'status' => 'skipped',
                'errors' => ['Duplicate order row was skipped by required action resolution.'],
                'payload' => [
                    ...$rowPayload,
                    'duplicate_decision' => 'skip',
                ],
            ]);
        }

        $this->refreshImportCounters($import);
    }

    private function cancelExistingDuplicateOrder(RequiredAction $action): void
    {
        $orderNumber = Arr::get($action->payload ?? [], 'order_number');
        if (! $orderNumber) {
            return;
        }

        Order::query()
            ->forTenant($action->tenant_id)
            ->where('order_number', $orderNumber)
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->update(['status' => 'cancelled']);
    }

    private function applyVariantToOrderItems(RequiredAction $action, ProductVariant $variant): void
    {
        if (! $action->order) {
            return;
        }

        $payload = $action->payload ?? [];
        $itemName = mb_strtolower((string) ($payload['item_name'] ?? $payload['name'] ?? ''));
        $itemSku = mb_strtolower((string) ($payload['item_sku'] ?? $payload['sku'] ?? ''));

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
    }

    private function revalidateImportRows(RequiredAction $action): void
    {
        if (! in_array($action->type, ['product_mapping_required', 'product_unavailable'], true)) {
            return;
        }

        $variantId = Arr::get($action->resolution_payload ?? [], 'product_variant_id')
            ?? Arr::get($action->payload ?? [], 'resolved_product_variant_id')
            ?? Arr::get($action->payload ?? [], 'resolution.product_variant_id');

        if (! $variantId) {
            return;
        }

        $payload = $action->payload ?? [];
        $orderNumber = Arr::get($payload, 'order_number');
        $itemSku = Arr::get($payload, 'item_sku');
        $itemName = Arr::get($payload, 'item_name');

        Import::query()
            ->forTenant($action->tenant_id)
            ->with('rows')
            ->get()
            ->each(function (Import $import) use ($orderNumber, $itemSku, $itemName, $variantId): void {
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
                            'matched_product_variant_id' => $variantId,
                            'resolved_by_required_action' => true,
                        ],
                    ]);
                }

                $ready = $import->rows()->where('status', 'ready')->count();
                $needsAction = $import->rows()->where('status', 'needs_action')->count();

                $import->update([
                    'valid_rows' => $ready,
                    'invalid_rows' => $needsAction,
                ]);
            });
    }

    private function refreshImportCounters(Import $import): void
    {
        $import->update([
            'valid_rows' => $import->rows()->whereIn('status', ['ready', 'committed'])->count(),
            'invalid_rows' => $import->rows()->whereIn('status', ['needs_action', 'error', 'skipped'])->count(),
        ]);
    }

    private function refreshOrderStatus(RequiredAction $action): void
    {
        if (! $action->order) {
            return;
        }

        $hasActiveActions = $action->order->requiredActions()
            ->whereIn('status', ['open', 'in_progress', 'escalated'])
            ->exists();

        if (! $hasActiveActions && $action->order->status === 'action_needed') {
            $action->order->update(['status' => 'verified']);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(RequiredAction $action, User $user, string $event, array $metadata = []): void
    {
        AuditLog::query()->create([
            'tenant_id' => $action->tenant_id,
            'user_id' => $user->id,
            'event' => $event,
            'auditable_type' => RequiredAction::class,
            'auditable_id' => $action->id,
            'metadata' => [
                'required_action_id' => $action->id,
                'type' => $action->type,
                'order_id' => $action->order_id,
                ...$metadata,
            ],
        ]);
    }

    private function freshAction(RequiredAction $action): RequiredAction
    {
        return $action->fresh(['order.items.variant.productType', 'comments.user', 'assignedTo']);
    }
}
