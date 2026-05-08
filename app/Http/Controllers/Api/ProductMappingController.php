<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductMapping;
use App\Models\ProductVariant;
use App\Services\ProductMappingEngine;
use App\Services\RequiredActionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductMappingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProductMapping::class);

        return response()->json(ProductMapping::query()
            ->forTenant($request->user()->tenant_id)
            ->with(['variant.productType', 'rules'])
            ->latest()
            ->get());
    }

    public function store(Request $request, ProductMappingEngine $engine, RequiredActionResolver $resolver): JsonResponse
    {
        Gate::authorize('create', ProductMapping::class);

        $validated = $this->validatedPayload($request);

        $tenantId = $request->user()->tenant_id;
        $this->assertVariantBelongsToTenant($validated['product_variant_id'], $tenantId);
        $this->assertRulesAreValid($validated['rules']);
        $this->assertNoDuplicateRules($engine, $tenantId, $validated['rules']);

        $mapping = ProductMapping::query()->create([
            'tenant_id' => $tenantId,
            'product_variant_id' => $validated['product_variant_id'],
            'name' => $validated['name'],
            'properties' => $validated['properties'] ?? [],
        ]);

        foreach ($validated['rules'] as $rule) {
            $mapping->rules()->create($rule);
        }

        $resolvedActions = $resolver->resolveForMapping($mapping);

        return response()->json([
            'mapping' => $mapping->load(['variant.productType', 'rules']),
            'resolved_actions' => $resolvedActions,
            'conflicts' => [],
        ], 201);
    }

    public function update(Request $request, ProductMapping $mapping, ProductMappingEngine $engine, RequiredActionResolver $resolver): JsonResponse
    {
        $this->assertMappingBelongsToTenant($request, $mapping);
        Gate::authorize('update', $mapping);

        $validated = $this->validatedPayload($request, $mapping);
        $tenantId = $request->user()->tenant_id;
        $this->assertVariantBelongsToTenant($validated['product_variant_id'], $tenantId);
        $this->assertRulesAreValid($validated['rules']);
        $this->assertNoDuplicateRules($engine, $tenantId, $validated['rules'], $mapping->id);

        $mapping->update([
            'product_variant_id' => $validated['product_variant_id'],
            'name' => $validated['name'],
            'properties' => $validated['properties'] ?? [],
        ]);

        $mapping->rules()->delete();
        foreach ($validated['rules'] as $rule) {
            $mapping->rules()->create($rule);
        }

        $resolvedActions = $resolver->resolveForMapping($mapping->fresh(['variant.productType', 'rules']));

        return response()->json([
            'mapping' => $mapping->fresh(['variant.productType', 'rules']),
            'resolved_actions' => $resolvedActions,
            'conflicts' => [],
        ]);
    }

    public function destroy(Request $request, ProductMapping $mapping): JsonResponse
    {
        $this->assertMappingBelongsToTenant($request, $mapping);
        Gate::authorize('delete', $mapping);

        $mapping->delete();

        return response()->json(['message' => 'Product mapping deleted.']);
    }

    public function simulate(Request $request, ProductMappingEngine $engine): JsonResponse
    {
        Gate::authorize('viewAny', ProductMapping::class);

        $validated = $request->validate([
            'item_name' => ['nullable', 'string', 'max:500'],
            'item_sku' => ['nullable', 'string', 'max:300'],
            'fulfillment_sku' => ['nullable', 'string', 'max:300'],
            'exclude_mapping_id' => ['nullable', 'integer'],
        ]);

        $result = $engine->simulate($request->user()->tenant_id, [
            'name' => $validated['item_name'] ?? null,
            'sku' => $validated['item_sku'] ?? null,
            'fulfillment_sku' => $validated['fulfillment_sku'] ?? null,
        ], $validated['exclude_mapping_id'] ?? null);

        return response()->json($result);
    }

    public function conflicts(Request $request, ProductMappingEngine $engine): JsonResponse
    {
        Gate::authorize('viewAny', ProductMapping::class);

        $validated = $request->validate([
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.field' => ['required', Rule::in(['sku', 'name', 'fulfillment_sku'])],
            'rules.*.operator' => ['required', Rule::in(['equals', 'contains', 'starts_with', 'regex'])],
            'rules.*.value' => ['required', 'string', 'max:300'],
            'exclude_mapping_id' => ['nullable', 'integer'],
        ]);

        $this->assertRulesAreValid($validated['rules']);

        return response()->json([
            'conflicts' => $engine->ruleConflicts(
                $request->user()->tenant_id,
                $validated['rules'],
                $validated['exclude_mapping_id'] ?? null,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request, ?ProductMapping $mapping = null): array
    {
        return $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'name' => ['required', 'string', 'max:180'],
            'properties' => ['nullable', 'array'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.field' => ['required', Rule::in(['sku', 'name', 'fulfillment_sku'])],
            'rules.*.operator' => ['required', Rule::in(['equals', 'contains', 'starts_with', 'regex'])],
            'rules.*.value' => ['required', 'string', 'max:300'],
            'rules.*.priority' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    private function assertVariantBelongsToTenant(int $variantId, int $tenantId): void
    {
        $variantBelongsToTenant = ProductVariant::query()
            ->whereKey($variantId)
            ->whereHas('productType', fn ($query) => $query->where('tenant_id', $tenantId))
            ->exists();

        if (! $variantBelongsToTenant) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['The selected production product does not belong to this tenant.'],
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     */
    private function assertRulesAreValid(array $rules): void
    {
        foreach ($rules as $index => $rule) {
            if (($rule['operator'] ?? null) !== 'regex') {
                continue;
            }

            if (@preg_match((string) $rule['value'], '') === false) {
                throw ValidationException::withMessages([
                    "rules.$index.value" => ['Regex rules must contain a valid PHP regular expression, including delimiters.'],
                ]);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     */
    private function assertNoDuplicateRules(ProductMappingEngine $engine, int $tenantId, array $rules, ?int $excludeMappingId = null): void
    {
        $duplicates = $engine->duplicateRuleMappings($tenantId, $rules, $excludeMappingId);

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'rules' => ['One or more rules duplicate an existing mapping: '.$duplicates->pluck('name')->join(', ')],
            ]);
        }
    }

    private function assertMappingBelongsToTenant(Request $request, ProductMapping $mapping): void
    {
        abort_unless($mapping->tenant_id === $request->user()->tenant_id, 404);
    }
}
