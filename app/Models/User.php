<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['tenant_id', 'name', 'email', 'role', 'active', 'invited_at', 'last_login_at', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['owner', 'admin', 'operations', 'support', 'viewer'];

    public const PERMISSIONS = [
        'owner' => ['view_dashboard', 'view_orders', 'manage_orders', 'manage_catalog', 'manage_mappings', 'manage_issues', 'manage_users'],
        'admin' => ['view_dashboard', 'view_orders', 'manage_orders', 'manage_catalog', 'manage_mappings', 'manage_issues', 'manage_users'],
        'operations' => ['view_dashboard', 'view_orders', 'manage_orders', 'manage_catalog', 'manage_mappings'],
        'support' => ['view_dashboard', 'view_orders', 'manage_issues'],
        'viewer' => ['view_dashboard', 'view_orders'],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'active' => 'boolean',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return self::PERMISSIONS[$this->role] ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function assignableRoles(): array
    {
        if ($this->isOwner()) {
            return self::ROLES;
        }

        return array_values(array_filter(self::ROLES, fn (string $role): bool => $role !== 'owner'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function notificationSubscriptions(): HasMany
    {
        return $this->hasMany(NotificationSubscription::class);
    }

    public function sentInvites(): HasMany
    {
        return $this->hasMany(UserInvite::class, 'invited_by_id');
    }
}
