<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id', 'product_variant_id', 'name', 'properties'])]
class ProductMapping extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(MappingRule::class);
    }
}
