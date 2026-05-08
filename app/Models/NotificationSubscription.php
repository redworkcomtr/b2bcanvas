<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'event', 'email', 'is_subscribed'])]
class NotificationSubscription extends Model
{
    protected function casts(): array
    {
        return ['is_subscribed' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('tenant_id', $tenantId));
    }
}
