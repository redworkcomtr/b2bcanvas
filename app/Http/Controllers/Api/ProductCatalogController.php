<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductOption;
use App\Models\ProductType;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->catalog($request));
    }

    public function storeType(Request $request): JsonResponse
    {
        $this->authorizeCatalog($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:80', Rule::unique('product_types', 'code')],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = ProductType::query()->create([
            ...$validated,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return response()->json($type->load(['variants', 'options']), 201);
    }

    public function updateType(Request $request, ProductType $productType): JsonResponse
    {
        $this->assertTypeBelongsToTenant($request, $productType);
        $this->authorizeCatalog($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'code' => ['sometimes', 'string', 'max:80', Rule::unique('product_types', 'code')->ignore($productType->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'string', 'max:2000'],
        ]);

        $productType->update($validated);

        return response()->json($productType->load(['variants', 'options']));
    }

    public function destroyType(Request $request, ProductType $productType): JsonResponse
    {
        $this->assertTypeBelongsToTenant($request, $productType);
        $this->authorizeCatalog($request);

        $productType->delete();

        return response()->json(['message' => 'Product type deleted.']);
    }

    public function storeVariant(Request $request, ProductType $productType): JsonResponse
    {
        $this->assertTypeBelongsToTenant($request, $productType);
        $this->authorizeCatalog($request);

        $variant = $productType->variants()->create($this->validatedVariant($request));

        return response()->json($variant->load('productType'), 201);
    }

    public function updateVariant(Request $request, ProductVariant $variant): JsonResponse
    {
        $this->assertVariantBelongsToTenant($request, $variant);
        $this->authorizeCatalog($request);

        $variant->update($this->validatedVariant($request, $variant));

        return response()->json($variant->fresh('productType'));
    }

    public function destroyVariant(Request $request, ProductVariant $variant): JsonResponse
    {
        $this->assertVariantBelongsToTenant($request, $variant);
        $this->authorizeCatalog($request);

        $variant->delete();

        return response()->json(['message' => 'Variant deleted.']);
    }

    public function storeOption(Request $request, ProductType $productType): JsonResponse
    {
        $this->assertTypeBelongsToTenant($request, $productType);
        $this->authorizeCatalog($request);

        $option = $productType->options()->create($this->validatedOption($request));

        return response()->json($option, 201);
    }

    public function updateOption(Request $request, ProductOption $option): JsonResponse
    {
        $this->assertOptionBelongsToTenant($request, $option);
        $this->authorizeCatalog($request);

        $option->update($this->validatedOption($request, $option));

        return response()->json($option->fresh());
    }

    public function destroyOption(Request $request, ProductOption $option): JsonResponse
    {
        $this->assertOptionBelongsToTenant($request, $option);
        $this->authorizeCatalog($request);

        $option->delete();

        return response()->json(['message' => 'Option deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedVariant(Request $request, ?ProductVariant $variant = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variant?->id)],
            'layout' => ['nullable', 'string', 'max:80'],
            'panel_count' => ['required', 'integer', 'min:1', 'max:12'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:10000000'],
            'image_sizes' => ['nullable', 'array'],
            'image_sizes.*' => ['string', 'max:80'],
            'panel_sizes' => ['nullable', 'array'],
            'panel_sizes.*' => ['string', 'max:80'],
            'template_url' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedOption(Request $request, ?ProductOption $option = null): array
    {
        return $request->validate([
            'group' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:100'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
    }

    /**
     * @return array<int, ProductType>
     */
    private function catalog(Request $request): array
    {
        return ProductType::query()
            ->forTenant($request->user()->tenant_id)
            ->with(['variants', 'options'])
            ->orderBy('name')
            ->get()
            ->all();
    }

    private function authorizeCatalog(Request $request): void
    {
        abort_unless($request->user()->hasPermission('manage_catalog'), 403);
    }

    private function assertTypeBelongsToTenant(Request $request, ProductType $productType): void
    {
        abort_unless($productType->tenant_id === $request->user()->tenant_id, 404);
    }

    private function assertVariantBelongsToTenant(Request $request, ProductVariant $variant): void
    {
        abort_unless($variant->productType()->where('tenant_id', $request->user()->tenant_id)->exists(), 404);
    }

    private function assertOptionBelongsToTenant(Request $request, ProductOption $option): void
    {
        abort_unless($option->productType()->where('tenant_id', $request->user()->tenant_id)->exists(), 404);
    }
}
