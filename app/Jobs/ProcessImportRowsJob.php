<?php

namespace App\Jobs;

use App\Events\NotificationRequested;
use App\Models\AuditLog;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\ProductVariant;
use App\Models\RequiredAction;
use App\Models\User;
use App\Services\ProductPricingService;
use App\Support\NotificationEventCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class ProcessImportRowsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $importId,
        public int $userId,
    ) {
        $this->onQueue('imports');
    }

    /** @return array{created_orders: int, skipped_rows: int, failed_rows: int} */
    public function handle(ProductPricingService $pricingService): array
    {
        $import = Import::query()->with('rows')->findOrFail($this->importId);
        $user = User::query()->findOrFail($this->userId);
        $created = 0;
        $skipped = 0;
        $failed = 0;

        $import->update(['status' => 'processing']);

        foreach ($import->rows()->where('status', 'ready')->orderBy('row_number')->get() as $row) {
            try {
                $result = $this->processRow($import, $row, $user, $pricingService);
                $created += $result === 'created' ? 1 : 0;
                $skipped += $result === 'skipped' ? 1 : 0;
            } catch (Throwable $exception) {
                $row->update([
                    'status' => 'error',
                    'errors' => [...($row->errors ?? []), $exception->getMessage()],
                ]);
                $failed++;
            }
        }

        $import->update([
            'status' => $import->rows()->whereIn('status', ['needs_action', 'error', 'skipped'])->exists() ? 'partial' : 'committed',
            'valid_rows' => $import->rows()->whereIn('status', ['ready', 'committed'])->count(),
            'invalid_rows' => $import->rows()->whereIn('status', ['needs_action', 'error', 'skipped'])->count(),
            'summary' => [
                ...($import->summary ?? []),
                'created_orders' => $created,
                'skipped_rows' => $skipped,
                'failed_rows' => $failed,
            ],
        ]);

        return [
            'created_orders' => $created,
            'skipped_rows' => $skipped,
            'failed_rows' => $failed,
        ];
    }

    private function processRow(Import $import, ImportRow $row, User $user, ProductPricingService $pricingService): string
    {
        $payload = $row->payload ?? [];
        $orderNumber = (string) Arr::get($payload, 'order_number', '');
        $duplicateDecision = (string) Arr::get($payload, 'duplicate_decision', '');
        $replacementOrderNumber = trim((string) Arr::get($payload, 'replacement_order_number', ''));

        if ($replacementOrderNumber !== '') {
            $orderNumber = $replacementOrderNumber;
            $payload['order_number'] = $replacementOrderNumber;
        }

        if ($duplicateDecision === 'skip') {
            $row->update([
                'status' => 'skipped',
                'payload' => $payload,
                'errors' => ['Duplicate order row was skipped by required action resolution.'],
            ]);

            return 'skipped';
        }

        if (Order::query()->forTenant($import->tenant_id)->where('order_number', $orderNumber)->exists()) {
            $row->update([
                'status' => 'needs_action',
                'payload' => $payload,
                'errors' => array_values(array_unique([...($row->errors ?? []), 'order_number already exists.'])),
            ]);

            $this->createOrRefreshRequiredAction(
                tenantId: $import->tenant_id,
                type: 'duplicate_order',
                title: 'Duplicate order in import',
                description: 'An import row cannot be created because this order number already exists.',
                payload: [
                    ...$payload,
                    'import_id' => $import->id,
                    'row_number' => $row->row_number,
                    'order_number' => $orderNumber,
                ],
            );

            NotificationRequested::dispatch(
                NotificationEventCatalog::ORDER_VALIDATION_FAILED,
                $import->tenant_id,
                [
                    'order_number' => $orderNumber,
                    'row_number' => $row->row_number,
                    'import_id' => $import->id,
                    'reason' => 'Order number already exists in the system.',
                    'error_summary' => 'Duplicate order number found during import commit.',
                ],
                $user,
            );

            return 'skipped';
        }

        $variant = ProductVariant::query()
            ->whereHas('productType', fn ($query) => $query->where('tenant_id', $import->tenant_id))
            ->with('productType')
            ->find(Arr::get($payload, 'matched_product_variant_id'));

        if (! $variant) {
            $row->update([
                'status' => 'needs_action',
                'errors' => array_values(array_unique([...($row->errors ?? []), 'Matched product variant is unavailable.'])),
            ]);

            $this->createOrRefreshRequiredAction(
                tenantId: $import->tenant_id,
                type: 'product_unavailable',
                title: 'Imported product is unavailable',
                description: 'The mapped production variant is no longer available. Select an alternate SKU before committing this row.',
                payload: [
                    ...$payload,
                    'import_id' => $import->id,
                    'row_number' => $row->row_number,
                ],
            );

            return 'skipped';
        }

        $quantity = (int) Arr::get($payload, 'quantity', 1);
        $price = $pricingService->priceItem($variant, $quantity, []);

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $import->tenant_id,
            'order_number' => $orderNumber,
            'status' => 'verified',
            'payment_status' => 'unpaid',
            'customer_name' => (string) Arr::get($payload, 'customer_name'),
            'shipping_service' => Arr::get($payload, 'shipping_service', 'Standard Ground'),
            'shipping_address' => [
                'line1' => Arr::get($payload, 'address_line_1'),
                'line2' => Arr::get($payload, 'address_line_2'),
                'city' => Arr::get($payload, 'city'),
                'state' => Arr::get($payload, 'state'),
                'postal_code' => Arr::get($payload, 'postal_code'),
                'country' => Arr::get($payload, 'country'),
            ],
            'order_date' => now(),
            'submitted_at' => now(),
            'totals' => ['subtotal_cents' => $price['subtotal_cents'], 'currency' => 'USD'],
        ]);

        $order->items()->create([
            'product_variant_id' => $variant->id,
            'item_name' => (string) Arr::get($payload, 'item_name'),
            'item_sku' => Arr::get($payload, 'item_sku'),
            'quantity' => $quantity,
            'product_code' => $variant->sku,
            'product_type' => $variant->productType?->name,
            'panel_summary' => implode(', ', $variant->panel_sizes ?? []),
            'design_images' => [],
            'options' => ['import_id' => $import->id, 'subtotal_cents' => $price['subtotal_cents']],
        ]);

        OrderStatusEvent::query()->create([
            'tenant_id' => $import->tenant_id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'from_status' => null,
            'to_status' => 'verified',
            'note' => 'Order created from import batch.',
            'metadata' => ['import_id' => $import->id, 'row_number' => $row->row_number],
        ]);

        $this->audit($import, $user, $order, $row);

        $row->update([
            'status' => 'committed',
            'payload' => [
                ...$payload,
                'order_id' => $order->id,
                'order_number' => $orderNumber,
            ],
            'errors' => [],
        ]);

        return 'created';
    }

    /** @param array<string, mixed> $payload */
    private function createOrRefreshRequiredAction(int $tenantId, string $type, string $title, string $description, array $payload): RequiredAction
    {
        $action = RequiredAction::query()
            ->forTenant($tenantId)
            ->whereNull('order_id')
            ->where('type', $type)
            ->where('status', '!=', 'resolved')
            ->where('title', $title)
            ->get()
            ->first(function (RequiredAction $existing) use ($payload): bool {
                $existingPayload = $existing->payload ?? [];

                return (string) ($existingPayload['order_number'] ?? '') === (string) ($payload['order_number'] ?? '')
                    && (int) ($existingPayload['import_id'] ?? 0) === (int) ($payload['import_id'] ?? 0)
                    && (int) ($existingPayload['row_number'] ?? 0) === (int) ($payload['row_number'] ?? 0);
            });

        if ($action) {
            return tap($action)->update([
                'description' => $description,
                'payload' => [...($action->payload ?? []), ...$payload],
                'last_activity_at' => now(),
            ]);
        }

        return RequiredAction::query()->create([
            'tenant_id' => $tenantId,
            'order_id' => null,
            'type' => $type,
            'status' => 'open',
            'priority' => 'normal',
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'last_activity_at' => now(),
        ]);
    }

    private function audit(Import $import, User $user, Order $order, ImportRow $row): void
    {
        AuditLog::query()->create([
            'tenant_id' => $import->tenant_id,
            'user_id' => $user->id,
            'event' => 'order.import_created',
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'metadata' => [
                'order_number' => $order->order_number,
                'import_id' => $import->id,
                'row_number' => $row->row_number,
            ],
        ]);
    }
}
