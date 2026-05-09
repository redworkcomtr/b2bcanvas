<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Issue;
use App\Models\NotificationSubscription;
use App\Models\Order;
use App\Models\ProductMapping;
use App\Models\ProductType;
use App\Models\RequiredAction;
use App\Models\SavedView;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user()->load(['tenant', 'notificationSubscriptions']);
        $tenant = $user->tenant;
        $tenantId = $tenant->id;

        $orderStatusCounts = Order::query()
            ->forTenant($tenantId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $importCounts = Import::query()
            ->forTenant($tenantId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $importRowCounts = ImportRow::query()
            ->join('imports', 'imports.id', '=', 'import_rows.import_id')
            ->where('imports.tenant_id', $tenantId)
            ->select('import_rows.status', DB::raw('COUNT(*) as total'))
            ->groupBy('import_rows.status')
            ->pluck('total', 'import_rows.status')
            ->toArray();

        $orderStatuses = ['draft', 'validation_failed', 'action_needed', 'verified', 'submitted', 'in_production', 'shipped', 'closed', 'cancelled'];
        $importStatuses = ['preview', 'partial', 'committed', 'error'];
        $importRowStatuses = ['ready', 'needs_action', 'committed', 'invalid', 'error'];

        $reportingMetrics = [
            'orders_total' => Order::query()->forTenant($tenantId)->count(),
            'orders_open_action_needed' => Order::query()->forTenant($tenantId)->whereNotIn('status', ['closed', 'cancelled'])->count(),
        ];

        foreach ($orderStatuses as $status) {
            $reportingMetrics["orders_{$status}"] = (int) Arr::get($orderStatusCounts, $status, 0);
        }

        foreach ($importStatuses as $status) {
            $reportingMetrics["imports_{$status}"] = (int) Arr::get($importCounts, $status, 0);
        }

        foreach ($importRowStatuses as $status) {
            $reportingMetrics["import_rows_{$status}"] = (int) Arr::get($importRowCounts, $status, 0);
        }

        $reportingMetrics['imports_total_rows'] = array_sum($importRowCounts);
        $readyRows = (int) $reportingMetrics['import_rows_ready'];
        $committedRows = (int) $reportingMetrics['import_rows_committed'];
        $reportingMetrics['imports_action_ratio'] = $readyRows + $committedRows > 0
            ? (int) round((($readyRows + $committedRows) / max(1, (int) $reportingMetrics['imports_total_rows']) * 100), 0)
            : 0;

        return response()->json([
            'tenant' => $tenant,
            'user' => $user,
            'abilities' => $user->permissions(),
            'metrics' => [
                'orders' => Order::query()->forTenant($tenantId)->count(),
                'actionNeeded' => Order::query()->forTenant($tenantId)->where('status', 'action_needed')->count(),
                'tickets' => Issue::query()->forTenant($tenantId)->where('type', 'ticket')->count(),
                'claims' => Issue::query()->forTenant($tenantId)->where('type', 'claim')->count(),
                'requiredActions' => RequiredAction::query()->forTenant($tenantId)->whereIn('status', ['open', 'in_progress', 'escalated'])->count(),
                'unreadNotes' => Issue::query()->forTenant($tenantId)->sum('unread_notes_count'),
                ...$reportingMetrics,
            ],
            'orders' => Order::query()
                ->forTenant($tenantId)
                ->with(['items.variant.productType', 'issues.comments.user', 'issues.assignedTo', 'requiredActions.comments.user'])
                ->latest('submitted_at')
                ->get(),
            'savedViews' => SavedView::query()
                ->forTenant($tenantId)
                ->where('scope', 'orders')
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
                ->orderByDesc('is_default')
                ->orderBy('name')
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
                ->with(['order', 'comments.user', 'assignedTo', 'claimResolution.user'])
                ->latest('last_activity_at')
                ->get(),
            'requiredActions' => RequiredAction::query()
                ->forTenant($tenantId)
                ->with(['order.items.variant.productType', 'comments.user', 'assignedTo'])
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
