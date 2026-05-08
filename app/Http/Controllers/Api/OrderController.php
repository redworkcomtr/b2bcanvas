<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\ProductMapping;
use App\Models\ProductVariant;
use App\Models\RequiredAction;
use App\Models\SavedView;
use App\Services\CsvOrderImportParser;
use App\Services\MappingRuleMatcher;
use App\Services\OrderStatusService;
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

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $validated = $request->validate([
            'order_number' => ['required', 'string', 'max:80'],
            'customer_name' => ['required', 'string', 'max:160'],
            'shipping_service' => ['nullable', 'string', 'max:120'],
            'shipping_address' => ['required', 'array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $this->assertItemVariantsBelongToTenant($validated['items'], $tenantId);

        $order = Order::query()->create([
            ...$validated,
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'status' => 'verified',
            'order_date' => now(),
            'submitted_at' => now(),
        ]);

        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'item_name' => $item['item_name'] ?? 'Configured print item',
                'item_sku' => $item['item_sku'] ?? null,
                'quantity' => $item['quantity'] ?? 1,
                'product_code' => $item['product_code'] ?? null,
                'product_type' => $item['product_type'] ?? null,
                'panel_summary' => $item['panel_summary'] ?? null,
                'design_images' => $item['design_images'] ?? [],
                'options' => $item['options'] ?? [],
            ]);
        }

        return response()->json($order->load('items.variant.productType'), 201);
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

    public function importPreview(Request $request, CsvOrderImportParser $parser, MappingRuleMatcher $matcher): JsonResponse
    {
        Gate::authorize('create', Order::class);

        $validated = $request->validate(['csv' => ['required', 'string']]);
        $parsed = $parser->parse($validated['csv']);
        $tenantId = $request->user()->tenant_id;
        $mappings = ProductMapping::query()
            ->forTenant($tenantId)
            ->with(['rules', 'variant.productType'])
            ->get();

        $rows = collect($parsed['rows'])->map(function (array $row) use ($matcher, $mappings, $tenantId): array {
            $payload = $row['payload'];
            $match = $matcher->bestMatch([
                'sku' => $payload['item_sku'] ?? null,
                'name' => $payload['item_name'] ?? null,
                'fulfillment_sku' => $payload['fulfillment_sku'] ?? null,
            ], $mappings);

            if (! $match) {
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
                'status' => $row['status'] === 'valid' && $match ? 'ready' : 'needs_action',
            ];
        });

        return response()->json([
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
