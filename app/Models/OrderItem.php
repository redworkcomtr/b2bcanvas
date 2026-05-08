<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_variant_id', 'item_name', 'item_sku', 'quantity', 'product_code', 'product_type', 'panel_summary', 'design_images', 'print_images', 'options'])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'design_images' => 'array',
            'print_images' => 'array',
            'options' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
