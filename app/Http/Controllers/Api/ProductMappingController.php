<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductMapping;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductMappingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ProductMapping::class);

        $validated = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'name' => ['required', 'string', 'max:180'],
            'properties' => ['nullable', 'array'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.field' => ['required', 'in:sku,name,fulfillment_sku'],
            'rules.*.operator' => ['required', 'in:equals,contains,starts_with,regex'],
            'rules.*.value' => ['required', 'string', 'max:300'],
            'rules.*.priority' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tenantId = $request->user()->tenant_id;
        $variantBelongsToTenant = ProductVariant::query()
            ->whereKey($validated['product_variant_id'])
            ->whereHas('productType', fn ($query) => $query->where('tenant_id', $tenantId))
            ->exists();

        if (! $variantBelongsToTenant) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['The selected production product does not belong to this tenant.'],
            ]);
        }

        $mapping = ProductMapping::query()->create([
            'tenant_id' => $tenantId,
            'product_variant_id' => $validated['product_variant_id'],
            'name' => $validated['name'],
            'properties' => $validated['properties'] ?? [],
        ]);

        foreach ($validated['rules'] as $rule) {
            $mapping->rules()->create($rule);
        }

        return response()->json($mapping->load(['variant.productType', 'rules']), 201);
    }
}
