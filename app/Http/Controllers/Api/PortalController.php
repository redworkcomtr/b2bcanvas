<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\NotificationSubscription;
use App\Models\Order;
use App\Models\ProductMapping;
use App\Models\ProductType;
use App\Models\RequiredAction;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load(['tenant', 'notificationSubscriptions']);
        $tenant = $user->tenant;
        $tenantId = $tenant->id;

        return response()->json([
            'tenant' => $tenant,
            'user' => $user,
            'abilities' => $user->permissions(),
            'metrics' => [
                'orders' => Order::query()->forTenant($tenantId)->count(),
                'actionNeeded' => Order::query()->forTenant($tenantId)->where('status', 'action_needed')->count(),
                'tickets' => Issue::query()->forTenant($tenantId)->where('type', 'ticket')->count(),
                'claims' => Issue::query()->forTenant($tenantId)->where('type', 'claim')->count(),
                'requiredActions' => RequiredAction::query()->forTenant($tenantId)->where('status', 'open')->count(),
                'unreadNotes' => Issue::query()->forTenant($tenantId)->sum('unread_notes_count'),
            ],
            'orders' => Order::query()
                ->forTenant($tenantId)
                ->with(['items.variant.productType', 'issues'])
                ->latest('submitted_at')
                ->get(),
            'productTypes' => ProductType::query()
                ->forTenant($tenantId)
                ->with(['variants', 'options'])
                ->orderBy('name')
                ->get(),
            'productMappings' => ProductMapping::query()
                ->forTenant($tenantId)
                ->with(['variant.productType', 'rules'])
                ->latest()
                ->get(),
            'issues' => Issue::query()
                ->forTenant($tenantId)
                ->with('order')
                ->latest('last_activity_at')
                ->get(),
            'requiredActions' => RequiredAction::query()
                ->forTenant($tenantId)
                ->with('order')
                ->latest('last_activity_at')
                ->get(),
            'notificationSubscriptions' => NotificationSubscription::query()
                ->where('user_id', $user->id)
                ->orderBy('event')
                ->get(),
            'users' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderByRaw("case role when 'owner' then 1 when 'admin' then 2 when 'operations' then 3 when 'support' then 4 else 5 end")
                ->orderBy('name')
                ->get(),
            'userInvites' => UserInvite::query()
                ->forTenant($tenantId)
                ->latest()
                ->get(),
        ]);
    }
}
