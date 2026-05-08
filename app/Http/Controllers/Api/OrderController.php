<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\MediaFile;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\ProductMapping;
use App\Models\ProductVariant;
use App\Models\RequiredAction;
use App\Models\SavedView;
use App\Services\CsvOrderImportParser;
use App\Services\MappingRuleMatcher;
use App\Services\OrderStatusService;
use App\Services\ProductPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $validated = $this->validatedIndexParams($request);
        $query = $this->filteredOrderQuery($request);

        $sort = $validated['sort'] ?? 'submitted_at';
        $direction = $validated['direction'] ?? 'desc';

        $orders = $query
            ->with(['items.variant.productType', 'issues', 'requiredActions'])
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->withQueryString();

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'from' => $orders->firstItem(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'to' => $orders->lastItem(),
                'total' => $orders->total(),
            ],
            'links' => [
                'next' => $orders->nextPageUrl(),
                'prev' => $orders->previousPageUrl(),
            ],
            'summary' => $this->orderSummary($request->user()->tenant_id),
        ]);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $order = Order::query()
            ->forTenant($request->user()->tenant_id)
            ->with([
                'items.variant.productType',
                'issues.comments.user',
                'requiredActions',
                'statusEvents.user',
                'mediaFiles',
                'auditLogs.user',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        Gate::authorize('view', $order);

        return response()->json($order);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Order::class);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders-export.csv"',
        ];

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order Number', 'Status', 'Customer', 'Submitted', 'Shipped', 'Shipping Service', 'Tracking Number', 'Items']);

            $this->filteredOrderQuery($request)
                ->with('items')
                ->orderBy('submitted_at', 'desc')
                ->chunk(100, function ($orders) use ($handle): void {
                    foreach ($orders as $order) {
                        fputcsv($handle, [
                            $order->order_number,
                            $order->status,
                            $order->customer_name,
                            $order->submitted_at?->toDateTimeString(),
                            $order->shipped_at?->toDateTimeString(),
                            $order->shipping_service,
                            $order->tracking_number,
                            $order->items->pluck('item_name')->join('; '),
                        ]);
                    }
                });

            fclose($handle);
        }, 'orders-export.csv', $headers);
    }

    public function savedViews(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        return response()->json(SavedView::query()
            ->forTenant($request->user()->tenant_id)
            ->where('scope', 'orders')
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get());
    }

    public function storeSavedView(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['nullable', 'array'],
            'sort' => ['nullable', 'array'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $view = SavedView::query()->updateOrCreate([
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'scope' => 'orders',
            'name' => $validated['name'],
        ], [
            'filters' => $validated['filters'] ?? [],
            'sort' => $validated['sort'] ?? [],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return response()->json($view, $view->wasRecentlyCreated ? 201 : 200);
    }

    public function destroySavedView(Request $request, SavedView $savedView): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);
        abort_unless($savedView->tenant_id === $request->user()->tenant_id && $savedView->scope === 'orders', 404);
        abort_unless($savedView->user_id === null || $savedView->user_id === $request->user()->id, 403);

        $savedView->delete();

        return response()->json(['message' => 'Saved view deleted.']);
    }

    public function store(Request $request, ProductPricingService $pricingService): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:80', 'unique:orders,order_number'],
            'status' => ['nullable', Rule::in(['draft', 'verified'])],
            'customer_name' => ['required', 'string', 'max:160'],
            'shipping_service' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.line1' => ['required', 'string', 'max:200'],
            'shipping_address.line2' => ['nullable', 'string', 'max:200'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.state' => ['nullable', 'string', 'max:80'],
            'shipping_address.postal_code' => ['required', 'string', 'max:40'],
            'shipping_address.country' => ['required', 'string', 'max:2'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.item_name' => ['nullable', 'string', 'max:500'],
            'items.*.item_sku' => ['nullable', 'string', 'max:300'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.artwork_media_file_id' => ['nullable', 'integer', 'exists:media_files,id'],
            'items.*.design_images' => ['nullable', 'array'],
            'items.*.design_images.*' => ['string', 'max:1000'],
            'items.*.options' => ['nullable', 'array'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $this->assertItemVariantsBelongToTenant($validated['items'], $tenantId);
        $this->assertMediaFilesBelongToTenant($validated['items'], $tenantId);

        $variants = ProductVariant::query()
            ->with('productType')
            ->whereIn('id', collect($validated['items'])->pluck('product_variant_id'))
            ->get()
            ->keyBy('id');
        $mediaFiles = MediaFile::query()
            ->forTenant($tenantId)
            ->whereIn('id', collect($validated['items'])->pluck('artwork_media_file_id')->filter())
            ->get()
            ->keyBy('id');

        $pricedItems = collect($validated['items'])->map(function (array $item) use ($variants, $mediaFiles, $pricingService): array {
            $variant = $variants->get($item['product_variant_id']);
            $quantity = (int) $item['quantity'];
            $options = $item['options'] ?? [];
            $price = $pricingService->priceItem($variant, $quantity, $options);
            $media = isset($item['artwork_media_file_id']) ? $mediaFiles->get($item['artwork_media_file_id']) : null;

            return [
                'payload' => $item,
                'variant' => $variant,
                'media' => $media,
                'price' => $price,
            ];
        });

        $status = $validated['status'] ?? 'verified';
        $subtotal = (int) $pricedItems->sum(fn (array $item): int => $item['price']['subtotal_cents']);

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'order_number' => $validated['order_number'],
            'status' => $status,
            'customer_name' => $validated['customer_name'],
            'shipping_service' => $validated['shipping_service'] ?? null,
            'shipping_address' => $validated['shipping_address'],
            'notes' => $validated['notes'] ?? null,
            'order_date' => now(),
            'submitted_at' => $status === 'draft' ? null : now(),
            'totals' => ['subtotal_cents' => $subtotal, 'currency' => 'USD'],
        ]);

        foreach ($pricedItems as $pricedItem) {
            $item = $pricedItem['payload'];
            $variant = $pricedItem['variant'];
            $media = $pricedItem['media'];
            $designImages = $item['design_images'] ?? [];
            if ($media) {
                $designImages[] = $media->url;
                $media->update([
                    'mediable_type' => Order::class,
                    'mediable_id' => $order->id,
                ]);
            }

            $order->items()->create([
                'product_variant_id' => $variant->id,
                'item_name' => $item['item_name'] ?? $variant->name,
                'item_sku' => $item['item_sku'] ?? null,
                'quantity' => (int) $item['quantity'],
                'product_code' => $variant->sku,
                'product_type' => $variant->productType?->name,
                'panel_summary' => implode(', ', $variant->panel_sizes ?? []),
                'design_images' => $designImages,
                'options' => [
                    ...($item['options'] ?? []),
                    'unit_price_cents' => $pricedItem['price']['unit_price_cents'],
                    'subtotal_cents' => $pricedItem['price']['subtotal_cents'],
                    'artwork_media_file_id' => $media?->id,
                ],
            ]);
        }

        OrderStatusEvent::query()->create([
            'tenant_id' => $tenantId,
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'from_status' => null,
            'to_status' => $status,
            'note' => 'Manual order created from wizard.',
            'metadata' => ['source' => 'new_order_wizard'],
        ]);
        $this->audit($request, $order, 'order.created', ['source' => 'new_order_wizard', 'subtotal_cents' => $subtotal]);

        return response()->json($this->freshOrder($order), 201);
    }

    public function updateAddress(Request $request, string $uuid): JsonResponse
    {
        $order = $this->tenantOrder($request, $uuid);
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'shipping_service' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:160'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.line1' => ['required', 'string', 'max:200'],
            'shipping_address.line2' => ['nullable', 'string', 'max:200'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.state' => ['nullable', 'string', 'max:80'],
            'shipping_address.postal_code' => ['required', 'string', 'max:40'],
            'shipping_address.country' => ['required', 'string', 'max:2'],
        ]);

        $before = $order->only(['customer_name', 'shipping_service', 'tracking_number', 'tracking_url', 'shipping_address']);
        $order->update($validated);

        $this->audit($request, $order, 'order.address_updated', [
            'before' => $before,
            'after' => $order->only(['customer_name', 'shipping_service', 'tracking_number', 'tracking_url', 'shipping_address']),
        ]);

        return response()->json($this->freshOrder($order));
    }

    public function updateNotes(Request $request, string $uuid): JsonResponse
    {
        $order = $this->tenantOrder($request, $uuid);
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $order->update(['notes' => $validated['notes'] ?? null]);
        $this->audit($request, $order, 'order.notes_updated', ['notes_length' => mb_strlen($order->notes ?? '')]);

        return response()->json($this->freshOrder($order));
    }

    public function transition(Request $request, string $uuid, OrderStatusService $statusService): JsonResponse
    {
        $order = $this->tenantOrder($request, $uuid);
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys($statusService->transitions()))],
            'note' => ['nullable', 'string', 'max:1000'],
            'tracking_number' => ['nullable', 'string', 'max:160'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
        ]);

        $event = $statusService->transition($order, $validated['status'], $request->user(), $validated['note'] ?? null, [
            'tracking_number' => $validated['tracking_number'] ?? null,
            'tracking_url' => $validated['tracking_url'] ?? null,
        ]);

        return response()->json([
            'order' => $this->freshOrder($order),
            'event' => $event->load('user'),
            'allowed_next_statuses' => $statusService->allowedNextStatuses($order->fresh()),
        ]);
    }

    public function importHistory(Request $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        return response()->json(Import::query()
            ->forTenant($request->user()->tenant_id)
            ->withCount('rows')
            ->latest()
            ->limit(20)
            ->get());
    }

    public function importPreview(Request $request, CsvOrderImportParser $parser, MappingRuleMatcher $matcher): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $validated = $request->validate([
            'csv' => ['required_without:file', 'nullable', 'string'],
            'file' => ['nullable', 'file', 'max:20480', 'mimetypes:text/csv,text/plain'],
            'filename' => ['nullable', 'string', 'max:180'],
        ]);
        $uploadedFile = $request->file('file');
        $contents = $uploadedFile
            ? file_get_contents($uploadedFile->getRealPath())
            : $validated['csv'];
        $parsed = $parser->parse((string) $contents);
        $tenantId = $request->user()->tenant_id;
        $mappings = ProductMapping::query()
            ->forTenant($tenantId)
            ->with(['rules', 'variant.productType'])
            ->get();

        $rows = collect($parsed['rows'])->map(function (array $row) use ($matcher, $mappings, $tenantId): array {
            $payload = $row['payload'];
            $rowErrors = $row['status'] === 'invalid' ? ['Row contains missing or invalid required fields.'] : [];
            if (($payload['order_number'] ?? null) && Order::query()->forTenant($tenantId)->where('order_number', $payload['order_number'])->exists()) {
                $rowErrors[] = 'order_number already exists.';
            }
            $match = $matcher->bestMatch([
                'sku' => $payload['item_sku'] ?? null,
                'name' => $payload['item_name'] ?? null,
                'fulfillment_sku' => $payload['fulfillment_sku'] ?? null,
            ], $mappings);

            if (! $match) {
                $rowErrors[] = 'No product mapping matched this item.';
                RequiredAction::query()->firstOrCreate([
                    'tenant_id' => $tenantId,
                    'order_id' => null,
                    'type' => 'product_mapping_required',
                    'title' => 'Product code mapping is required',
                    'description' => 'No product mapping matched imported item "'.($payload['item_name'] ?? 'Unknown item').'".',
                ], [
                    'payload' => $payload,
                    'last_activity_at' => now(),
                ]);
            }

            return [
                ...$row,
                'matched_mapping' => $match?->load('variant.productType'),
                'matched_product_variant_id' => $match?->product_variant_id,
                'errors' => $rowErrors,
                'status' => $rowErrors === [] && $match ? 'ready' : 'needs_action',
            ];
        });

        $import = Import::query()->create([
            'tenant_id' => $tenantId,
            'filename' => $validated['filename'] ?? ($uploadedFile?->getClientOriginalName() ?? 'pasted-import.csv'),
            'status' => 'preview',
            'total_rows' => $rows->count(),
            'valid_rows' => $rows->where('status', 'ready')->count(),
            'invalid_rows' => $rows->where('status', 'needs_action')->count(),
            'summary' => [
                'headers' => $parsed['headers'],
                'parser_errors' => $parsed['errors'],
            ],
        ]);

        foreach ($rows as $row) {
            ImportRow::query()->create([
                'import_id' => $import->id,
                'row_number' => $row['row_number'],
                'status' => $row['status'],
                'payload' => [
                    ...$row['payload'],
                    'matched_product_variant_id' => $row['matched_product_variant_id'],
                    'matched_mapping_id' => $row['matched_mapping']['id'] ?? null,
                ],
                'errors' => $row['errors'],
            ]);
        }

        return response()->json([
            'import_id' => $import->id,
            'headers' => $parsed['headers'],
            'rows' => $rows,
            'errors' => $parsed['errors'],
            'summary' => [
                'total' => $rows->count(),
                'ready' => $rows->where('status', 'ready')->count(),
                'needs_action' => $rows->where('status', 'needs_action')->count(),
            ],
        ]);
    }

    public function commitImport(Request $request, Import $import, ProductPricingService $pricingService): JsonResponse
    {
        Gate::authorize('create', Order::class);
        abort_unless($import->tenant_id === $request->user()->tenant_id, 404);

        $created = 0;
        $skipped = 0;

        $import->load('rows');
        foreach ($import->rows->where('status', 'ready') as $row) {
            $payload = $row->payload;
            if (Order::query()->forTenant($import->tenant_id)->where('order_number', $payload['order_number'])->exists()) {
                $row->update(['status' => 'needs_action', 'errors' => [...($row->errors ?? []), 'order_number already exists.']]);
                $skipped++;
                continue;
            }

            $variant = ProductVariant::query()->with('productType')->findOrFail($payload['matched_product_variant_id']);
            $quantity = (int) ($payload['quantity'] ?? 1);
            $price = $pricingService->priceItem($variant, $quantity, []);

            $order = Order::query()->create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $import->tenant_id,
                'order_number' => $payload['order_number'],
                'status' => 'verified',
                'customer_name' => $payload['customer_name'],
                'shipping_service' => $payload['shipping_service'] ?? 'Standard Ground',
                'shipping_address' => [
                    'line1' => $payload['address_line_1'],
                    'city' => $payload['city'],
                    'state' => $payload['state'] ?? null,
                    'postal_code' => $payload['postal_code'],
                    'country' => $payload['country'],
                ],
                'order_date' => now(),
                'submitted_at' => now(),
                'totals' => ['subtotal_cents' => $price['subtotal_cents'], 'currency' => 'USD'],
            ]);
            $order->items()->create([
                'product_variant_id' => $variant->id,
                'item_name' => $payload['item_name'],
                'item_sku' => $payload['item_sku'] ?? null,
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
                'user_id' => $request->user()->id,
                'from_status' => null,
                'to_status' => 'verified',
                'note' => 'Order created from import batch.',
                'metadata' => ['import_id' => $import->id, 'row_number' => $row->row_number],
            ]);
            $this->audit($request, $order, 'order.import_created', ['import_id' => $import->id, 'row_number' => $row->row_number]);
            $row->update(['status' => 'committed']);
            $created++;
        }

        $import->update([
            'status' => $import->rows()->where('status', 'needs_action')->exists() ? 'partial' : 'committed',
            'valid_rows' => $import->rows()->whereIn('status', ['ready', 'committed'])->count(),
            'invalid_rows' => $import->rows()->where('status', 'needs_action')->count(),
            'summary' => [
                ...($import->summary ?? []),
                'created_orders' => $created,
                'skipped_rows' => $skipped,
            ],
        ]);

        return response()->json([
            'import' => $import->fresh('rows'),
            'created_orders' => $created,
            'skipped_rows' => $skipped,
        ]);
    }

    public function importErrorReport(Request $request, Import $import): StreamedResponse
    {
        Gate::authorize('create', Order::class);
        abort_unless($import->tenant_id === $request->user()->tenant_id, 404);

        return response()->streamDownload(function () use ($import): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['row_number', 'order_number', 'status', 'errors']);
            $import->rows()->where('status', 'needs_action')->orderBy('row_number')->each(function (ImportRow $row) use ($handle): void {
                fputcsv($handle, [
                    $row->row_number,
                    $row->payload['order_number'] ?? '',
                    $row->status,
                    implode('; ', $row->errors ?? []),
                ]);
            });
            fclose($handle);
        }, 'import-errors-'.$import->id.'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function assertItemVariantsBelongToTenant(array $items, int $tenantId): void
    {
        $variantIds = collect($items)
            ->pluck('product_variant_id')
            ->filter()
            ->unique()
            ->values();

        if ($variantIds->isEmpty()) {
            return;
        }

        $allowedCount = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->whereHas('productType', fn ($query) => $query->where('tenant_id', $tenantId))
            ->count();

        if ($allowedCount !== $variantIds->count()) {
            throw ValidationException::withMessages([
                'items' => ['One or more product variants do not belong to this tenant.'],
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function assertMediaFilesBelongToTenant(array $items, int $tenantId): void
    {
        $mediaIds = collect($items)
            ->pluck('artwork_media_file_id')
            ->filter()
            ->unique()
            ->values();

        if ($mediaIds->isEmpty()) {
            return;
        }

        $allowedCount = MediaFile::query()
            ->forTenant($tenantId)
            ->whereIn('id', $mediaIds)
            ->where('collection', 'artwork')
            ->count();

        if ($allowedCount !== $mediaIds->count()) {
            throw ValidationException::withMessages([
                'items' => ['One or more artwork files do not belong to this tenant.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedIndexParams(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'max:80'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(['submitted_at', 'order_date', 'shipped_at', 'order_number', 'customer_name', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function filteredOrderQuery(Request $request)
    {
        $validated = $this->validatedIndexParams($request);

        return Order::query()
            ->forTenant($request->user()->tenant_id)
            ->when($validated['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('order_number', 'like', "%{$term}%")
                        ->orWhere('customer_name', 'like', "%{$term}%")
                        ->orWhere('shipping_service', 'like', "%{$term}%")
                        ->orWhereHas('items', function ($query) use ($term): void {
                            $query->where('item_name', 'like', "%{$term}%")
                                ->orWhere('item_sku', 'like', "%{$term}%")
                                ->orWhere('product_code', 'like', "%{$term}%");
                        });
                });
            })
            ->when(($validated['status'] ?? null) && $validated['status'] !== 'all', fn ($query) => $query->where('status', $validated['status']))
            ->when($validated['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('submitted_at', '<=', $date));
    }

    /**
     * @return array<string, int>
     */
    private function orderSummary(int $tenantId): array
    {
        return [
            'total' => Order::query()->forTenant($tenantId)->count(),
            'action_needed' => Order::query()->forTenant($tenantId)->where('status', 'action_needed')->count(),
            'verified' => Order::query()->forTenant($tenantId)->where('status', 'verified')->count(),
            'in_production' => Order::query()->forTenant($tenantId)->where('status', 'in_production')->count(),
            'shipped' => Order::query()->forTenant($tenantId)->where('status', 'shipped')->count(),
        ];
    }

    private function tenantOrder(Request $request, string $uuid): Order
    {
        return Order::query()
            ->forTenant($request->user()->tenant_id)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    private function freshOrder(Order $order): Order
    {
        return $order->fresh([
            'items.variant.productType',
            'issues.comments.user',
            'requiredActions',
            'statusEvents.user',
            'mediaFiles',
            'auditLogs.user',
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function audit(Request $request, Order $order, string $event, array $metadata = []): void
    {
        AuditLog::query()->create([
            'tenant_id' => $order->tenant_id,
            'user_id' => $request->user()->id,
            'event' => $event,
            'auditable_type' => Order::class,
            'auditable_id' => $order->id,
            'metadata' => [
                'order_number' => $order->order_number,
                ...$metadata,
            ],
        ]);
    }
}
