<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\NotificationSubscription;
use App\Models\Order;
use App\Models\ProductMapping;
use App\Models\ProductType;
use App\Models\RequiredAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class PortalController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $tenant = Tenant::query()->first();
        $user = User::query()->with('notificationSubscriptions')->first();

        return response()->json([
            'tenant' => $tenant,
            'user' => $user,
            'metrics' => [
                'orders' => Order::query()->count(),
                'actionNeeded' => Order::query()->where('status', 'action_needed')->count(),
                'tickets' => Issue::query()->where('type', 'ticket')->count(),
                'claims' => Issue::query()->where('type', 'claim')->count(),
                'requiredActions' => RequiredAction::query()->where('status', 'open')->count(),
                'unreadNotes' => Issue::query()->sum('unread_notes_count'),
            ],
            'orders' => Order::query()
                ->with(['items.variant.productType', 'issues'])
                ->latest('submitted_at')
                ->get(),
            'productTypes' => ProductType::query()
                ->with(['variants', 'options'])
                ->orderBy('name')
                ->get(),
            'productMappings' => ProductMapping::query()
                ->with(['variant.productType', 'rules'])
                ->latest()
                ->get(),
            'issues' => Issue::query()
                ->with('order')
                ->latest('last_activity_at')
                ->get(),
            'requiredActions' => RequiredAction::query()
                ->with('order')
                ->latest('last_activity_at')
                ->get(),
            'notificationSubscriptions' => NotificationSubscription::query()->get(),
        ]);
    }
}
