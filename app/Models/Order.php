<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uuid', 'tenant_id', 'order_number', 'status', 'order_date', 'submitted_at', 'shipped_at', 'shipping_service', 'customer_name', 'shipping_address', 'totals', 'notes'])]
class Order extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'order_date' => 'datetime',
            'submitted_at' => 'datetime',
            'shipped_at' => 'datetime',
            'shipping_address' => 'array',
            'totals' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function requiredActions(): HasMany
    {
        return $this->hasMany(RequiredAction::class);
    }
}
