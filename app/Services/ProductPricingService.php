<?php

namespace App\Services;

use App\Models\ProductOption;
use App\Models\ProductVariant;

class ProductPricingService
{
    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function priceItem(ProductVariant $variant, int $quantity, array $options = []): array
    {
        $typeId = $variant->product_type_id;
        $selectedOptionNames = collect($options)
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->values();

        $optionTotal = ProductOption::query()
            ->where('product_type_id', $typeId)
            ->whereIn('name', $selectedOptionNames)
            ->sum('price_cents');

        $unitPrice = (int) $variant->price_cents + (int) $optionTotal;

        return [
            'unit_price_cents' => $unitPrice,
            'option_price_cents' => (int) $optionTotal,
            'subtotal_cents' => $unitPrice * $quantity,
            'currency' => 'USD',
        ];
    }
}
