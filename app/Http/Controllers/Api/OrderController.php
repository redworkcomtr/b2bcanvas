<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductMapping;
use App\Models\ProductVariant;
use App\Models\RequiredAction;
use App\Services\CsvOrderImportParser;
use App\Services\MappingRuleMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Order::class);

        return response()->json(Order::query()
            ->forTenant($request->user()->tenant_id)
            ->with(['items.variant.productType', 'issues'])
            ->latest('submitted_at')
            ->paginate(25));
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $order = Order::query()
            ->forTenant($request->user()->tenant_id)
            ->with(['items.variant.productType', 'issues.comments'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        Gate::authorize('view', $order);

        return response()->json($order);
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
}
