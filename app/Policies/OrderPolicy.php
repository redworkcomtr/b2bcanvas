<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_orders');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasPermission('view_orders') && $user->tenant_id === $order->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage_orders');
    }
}
