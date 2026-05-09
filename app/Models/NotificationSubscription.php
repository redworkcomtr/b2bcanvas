<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'event', 'email', 'is_subscribed', 'unsubscribe_token'])]
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

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (! $subscription->unsubscribe_token) {
                $subscription->unsubscribe_token = static::generateUnsubscribeToken();
            }
        });
    }

    public function ensureUnsubscribeToken(): string
    {
        if ($this->unsubscribe_token) {
            return $this->unsubscribe_token;
        }

        $this->unsubscribe_token = static::generateUnsubscribeToken();
        $this->save();

        return $this->unsubscribe_token;
    }

    private static function generateUnsubscribeToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::query()->where('unsubscribe_token', $token)->exists());

        return $token;
    }
}
