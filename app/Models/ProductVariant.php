<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_type_id', 'name', 'sku', 'layout', 'panel_count', 'price_cents', 'image_sizes', 'panel_sizes', 'template_url'])]
class ProductVariant extends Model
{
    protected function casts(): array
    {
        return [
            'image_sizes' => 'array',
            'panel_sizes' => 'array',
            'price_cents' => 'integer',
        ];
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }
}
