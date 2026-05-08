<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_type_id', 'group', 'name', 'code', 'price_cents'])]
class ProductOption extends Model
{
    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::class);
    }
}
