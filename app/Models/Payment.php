<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'order_id',
    'user_id',
    'provider',
    'provider_payment_intent_id',
    'amount_cents',
    'currency',
    'status',
    'metadata',
    'last_error',
])]
class Payment extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'amount_cents' => 'int',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
