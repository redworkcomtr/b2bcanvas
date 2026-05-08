<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function invite(User $user): bool
    {
        return $user->hasPermission('manage_users');
    }

    public function update(User $user, User $target): bool
    {
        if (! $user->hasPermission('manage_users')) {
            return false;
        }

        if ($user->tenant_id !== $target->tenant_id) {
            return false;
        }

        if ($target->isOwner() && ! $user->isOwner()) {
            return false;
        }

        return true;
    }
}
