<?php

namespace App\Policies;

use App\Models\ProductMapping;
use App\Models\User;

class ProductMappingPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('manage_mappings');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage_mappings');
    }

    public function update(User $user, ProductMapping $mapping): bool
    {
        return $user->hasPermission('manage_mappings') && $user->tenant_id === $mapping->tenant_id;
    }

    public function delete(User $user, ProductMapping $mapping): bool
    {
        return $this->update($user, $mapping);
    }
}
