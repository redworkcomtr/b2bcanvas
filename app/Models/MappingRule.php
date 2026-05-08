<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_mapping_id', 'field', 'operator', 'value', 'priority'])]
class MappingRule extends Model
{
    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ProductMapping::class, 'product_mapping_id');
    }
}
