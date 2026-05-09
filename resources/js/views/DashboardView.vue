<script setup lang="ts">
import {
    AlertTriangle,
    ArrowRightLeft,
    BarChart3,
    ClipboardList,
    FileWarning,
    Globe,
    LifeBuoy,
    Map,
    PackageCheck,
    Timer,
    Upload,
    CheckCircle2,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';

import StatCard from '@/components/portal/StatCard.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import DataTable from '@/components/ui/Table.vue';
import Tabs from '@/components/ui/Tabs.vue';
import { dateLabel, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';

const store = usePortalStore();
const activeActionStatuses = ['open', 'in_progress', 'escalated'];
const orderStatuses = ['draft', 'validation_failed', 'action_needed', 'verified', 'submitted', 'in_production', 'shipped', 'closed', 'cancelled'];
const importStatuses = ['preview', 'partial', 'committed'];
const profileTabs = [
    { label: 'Operations', value: 'operations' as const },
    { label: 'Executive', value: 'executive' as const },
];

const dashboardProfile = ref<'operations' | 'executive'>('operations');

const metrics = computed(() => store.metrics as Record<string, number>);
const totalOrders = computed(() => Math.max(1, metrics.value.orders_total ?? 0));
const latestOrders = computed(() => store.orders.slice(0, 5));

const isOperationsUser = computed(() => {
    const role = store.user?.role;
    return role === 'owner' || role === 'admin' || role === 'operations';
});

const defaultProfile = computed(() => (isOperationsUser.value ? 'operations' : 'executive'));

if (defaultProfile.value === 'executive') {
    dashboardProfile.value = 'executive';
}

const statusDistribution = computed(() => orderStatuses.map((status) => {
    const key = `orders_${status}`;
    const value = metrics.value[key] ?? 0;

    return {
        key,
        label: status.replace('_', ' '),
        value,
        ratio: Math.round((value / totalOrders.value) * 100),
    };
}));

const importHealth = computed(() => {
    const importRowsReady = metrics.value.import_rows_ready ?? 0;
    const importRowsCommitted = metrics.value.import_rows_committed ?? 0;
    const importRowsNeedsAction = metrics.value.import_rows_needs_action ?? 0;
    const totalRows = importRowsReady + importRowsCommitted + importRowsNeedsAction;

    return {
        batches: {
            preview: metrics.value.imports_preview ?? 0,
            partial: metrics.value.imports_partial ?? 0,
            committed: metrics.value.imports_committed ?? 0,
        },
        rows: {
            ready: importRowsReady,
            committed: importRowsCommitted,
            needsAction: importRowsNeedsAction,
            total: totalRows,
        },
        actionRate: totalRows > 0
            ? Math.round(((importRowsReady + importRowsCommitted) / totalRows) * 100)
            : (metrics.value.imports_action_ratio ?? 0),
    };
});

const operationalMetrics = computed(() => {
    const openActionQueue = (metrics.value.orders_action_needed ?? 0)
        + (metrics.value.orders_validation_failed ?? 0)
        + (metrics.value.orders_draft ?? 0);
    const inProduction = metrics.value.orders_in_production ?? 0;
    const paymentUnpaid = store.orders.filter((order) => order.payment_status === 'unpaid').length;
    const requiredActions = metrics.value.requiredActions ?? 0;
    const waitingIssues = store.issues.filter((item) => item.unread_notes_count > 0).length;
    const openClaims = metrics.value.claims ?? 0;

    return {
        openActionQueue,
        inProduction,
        paymentUnpaid,
        requiredActions,
        waitingIssues,
        openClaims,
    };
});

const executiveMetrics = computed(() => {
    const shipped = metrics.value.orders_shipped ?? 0;
    const cancelled = metrics.value.orders_cancelled ?? 0;
    const nonTerminal = store.orders.filter((order) => !['closed', 'cancelled'].includes(order.status));
    const oldestOpenOrder = nonTerminal
        .map((order) => order.submitted_at)
        .filter((value): value is string => Boolean(value))
        .map((value) => (Date.now() - new Date(value).getTime()) / 86400000)
        .filter((days) => Number.isFinite(days))
        .sort((a, b) => b - a)[0];

    const grossOpenValue = nonTerminal.reduce((sum, order) => sum + (order.totals?.subtotal_cents ?? 0), 0);
    const shippedRate = Math.round((shipped / totalOrders.value) * 100);
    const closedRate = Math.round((metrics.value.orders_closed ?? 0) / totalOrders.value * 100);
    const canceledRate = Math.round((cancelled / totalOrders.value) * 100);
    const openActionQueue = (metrics.value.orders_action_needed ?? 0) + (metrics.value.orders_validation_failed ?? 0);

    return {
        shippedRate,
        closedRate,
        canceledRate,
        oldestOpenDays: oldestOpenOrder ? Math.round(oldestOpenOrder) : 0,
        grossOpenValue,
        openActionQueue,
        tickets: metrics.value.tickets ?? 0,
        importActionRatio: importHealth.value.actionRate,
        totalImportsRows: metrics.value.imports_total_rows ?? 0,
        closedRateValue: closedRate,
        canceledRateValue: canceledRate,
    };
});

const importRowRatio = computed(() => {
    const totalRows = importHealth.value.rows.total;
    const blockedRows = importHealth.value.rows.needsAction;

    if (!totalRows) {
        return 0;
    }

    return Math.round((Math.max(0, totalRows - blockedRows) / totalRows) * 100);
});

const workQueue = computed(() => [
    ...store.requiredActions.filter((action) => activeActionStatuses.includes(action.status)).slice(0, 3).map((action) => ({
        label: action.title,
        caption: action.description,
        to: '/issues/actions',
        tone: 'warning' as const,
    })),
    ...store.issues.filter((issue) => issue.unread_notes_count > 0).slice(0, 3).map((issue) => ({
        label: `${issue.type === 'ticket' ? 'Ticket' : 'Claim'} update`,
        caption: issue.description,
        to: issue.type === 'ticket' ? '/issues/tickets' : '/issues/claims',
        tone: 'info' as const,
    })),
]);

const kpiCards = computed(() => {
    if (dashboardProfile.value === 'executive') {
        return [
            {
                label: 'Active Orders',
                value: metrics.value.orders_open_action_needed ?? 0,
                caption: 'Active orders not yet closed',
                icon: Globe,
            },
            {
                label: 'Shipped Ratio',
                value: `${executiveMetrics.value.shippedRate}%`,
                caption: `${executiveMetrics.value.closedRateValue}% closed · ${executiveMetrics.value.canceledRateValue}% cancelled`,
                icon: ArrowRightLeft,
            },
            {
                label: 'Support Pressure',
                value: executiveMetrics.value.tickets,
                caption: 'Open support/claim-facing items',
                icon: LifeBuoy,
            },
            {
                label: 'Import Health',
                value: `${executiveMetrics.value.importActionRatio}%`,
                caption: `${executiveMetrics.value.totalImportsRows} rows analyzed`,
                icon: CheckCircle2,
            },
            {
                label: 'Gross Open Value',
                value: `$${(executiveMetrics.value.grossOpenValue / 100).toFixed(2)}`,
                caption: 'Open order pipeline value',
                icon: Wallet,
            },
            {
                label: 'Oldest Open Order',
                value: `${executiveMetrics.value.oldestOpenDays}d`,
                caption: 'Longest open age',
                icon: Timer,
            },
        ];
    }

    return [
        {
            label: 'Orders',
            value: metrics.value.orders ?? 0,
            caption: 'Across all active statuses',
            icon: ClipboardList,
        },
        {
            label: 'Action Needed',
            value: metrics.value.actionNeeded ?? 0,
            caption: 'Blocked by customer data',
            icon: AlertTriangle,
        },
        {
            label: 'Open Work Queue',
            value: operationalMetrics.value.openActionQueue,
            caption: 'Draft / action / validation',
            icon: Map,
        },
        {
            label: 'Required Actions',
            value: operationalMetrics.value.requiredActions,
            caption: 'Unresolved production blockers',
            icon: FileWarning,
        },
        {
            label: 'Import Health',
            value: `${importHealth.value.actionRate}%`,
            caption: 'Ready + committed import rows',
            icon: CheckCircle2,
        },
        {
            label: 'Unpaid Orders',
            value: operationalMetrics.value.paymentUnpaid,
            caption: 'Manual order capture pending',
            icon: Wallet,
        },
    ];
});
</script>

<template>
    <div class="grid gap-6">
        <div class="flex flex-col gap-2">
            <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Workspace</p>
            <h2 class="text-3xl font-bold tracking-tight text-slate-950">Production command center</h2>
            <p class="max-w-3xl text-slate-600">
                Manage B2B orders, product mappings, import exceptions, support tickets, claims, and operational notifications from one Laravel + Vue workspace.
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <Tabs :tabs="profileTabs" v-model="dashboardProfile" />
            <span class="text-sm text-slate-500">Active view: {{ dashboardProfile === 'operations' ? 'Operations' : 'Executive' }}</span>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <StatCard
                v-for="item in kpiCards"
                :key="item.label"
                :label="item.label"
                :value="item.value"
                :caption="item.caption"
                :icon="item.icon"
            />
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card>
                <h3 class="mb-4 text-lg font-bold text-slate-950">Order Status Distribution</h3>
                <p class="mb-3 text-sm text-slate-500">Live mix across fulfillment lifecycle.</p>
                <div class="grid gap-3">
                    <div v-for="item in statusDistribution" :key="item.key">
                        <div class="mb-1 flex items-center justify-between text-sm text-slate-600">
                            <span class="font-semibold text-slate-700">{{ item.label }}</span>
                            <span>{{ item.value }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100">
                            <div class="h-2 rounded-full bg-teal-600" :style="{ width: `${Math.min(item.ratio, 100)}%` }" />
                        </div>
                    </div>
                </div>
            </Card>

            <Card>
                <h3 class="mb-4 text-lg font-bold text-slate-950">Import Health</h3>
                <p class="mb-3 text-sm text-slate-500">Import reliability and backlog profile across all batches.</p>
                <div class="grid gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Batch States</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <Badge tone="info" v-for="status in importStatuses" :key="`batch-${status}`">
                                {{ status }}: {{ importHealth.batches[status as keyof typeof importHealth.batches] }}
                            </Badge>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-700">Rows</p>
                        <p class="mt-2 text-sm text-slate-600">Ready {{ importHealth.rows.ready }} · Committed {{ importHealth.rows.committed }}</p>
                        <p class="text-sm text-slate-600">Needs action {{ importHealth.rows.needsAction }} · Total {{ importHealth.rows.total }}</p>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-slate-700">Actionable import ratio</p>
                        <div class="mt-2 h-2 rounded-full bg-slate-100">
                            <div class="h-2 rounded-full bg-emerald-600" :style="{ width: `${importHealth.actionRate}%` }" />
                        </div>
                        <p class="mt-1 text-xs text-slate-500">{{ importHealth.actionRate }}% of import rows are actionable</p>
                    </div>
                </div>
            </Card>

            <Card>
                <h3 class="mb-4 text-lg font-bold text-slate-950">Operations Snapshot</h3>
                <p class="mb-3 text-sm text-slate-500">What needs operator attention now.</p>
                <div class="grid gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Unpaid vs Action needed</p>
                        <p class="text-sm text-slate-600">{{ operationalMetrics.paymentUnpaid }} unpaid · {{ metrics.orders_open_action_needed ?? 0 }} action-needed</p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Active work items</p>
                        <p class="text-sm text-slate-600">
                            Required actions: {{ operationalMetrics.requiredActions }} · Unread support: {{ operationalMetrics.waitingIssues }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-700">Open production</p>
                        <p class="text-sm text-slate-600">{{ operationalMetrics.inProduction }} orders in production flow.</p>
                    </div>
                    <div class="rounded-md border border-dashed border-slate-200 px-3 py-2 text-xs text-slate-500">
                        <p class="font-semibold text-slate-700">Import row quality</p>
                        <p>{{ importRowRatio }}% of rows are ready for commitment.</p>
                    </div>
                </div>
            </Card>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_.9fr]">
            <Card>
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-950">Latest Orders</h3>
                        <p class="text-sm text-slate-500">Recent orders with status, service, and customer context.</p>
                    </div>
                    <RouterLink to="/orders">
                        <Button variant="outline" size="sm">
                            <BarChart3 class="h-4 w-4" />
                            View all
                        </Button>
                    </RouterLink>
                </div>

                <DataTable min-width="900px">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3">Submitted</th>
                            <th class="px-4 py-3">Items</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr v-for="order in latestOrders" :key="order.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-semibold text-slate-950">
                                <RouterLink :to="`/orders/${order.uuid}`">{{ order.order_number }}</RouterLink>
                            </td>
                            <td class="px-4 py-3"><Badge :tone="statusTone(order.status)">{{ order.status.replace('_', ' ') }}</Badge></td>
                            <td class="px-4 py-3 text-slate-600">{{ order.customer_name }}</td>
                            <td class="px-4 py-3">
                                <Badge :tone="order.payment_status === 'paid' || order.payment_status === 'not_required' ? 'success' : 'warning'">
                                    {{ order.payment_status ?? 'pending' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ dateLabel(order.submitted_at) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ order.items?.length ?? 0 }}</td>
                        </tr>
                    </tbody>
                </DataTable>
            </Card>

            <Card>
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-950">Work Queue</h3>
                        <p class="text-sm text-slate-500">The items most likely to stop production.</p>
                    </div>
                    <Upload class="h-5 w-5 text-orange-600" />
                </div>

                <div class="grid gap-3">
                    <RouterLink
                        v-for="item in workQueue"
                        :key="`${item.label}-${item.caption}`"
                        :to="item.to"
                        class="rounded-md border border-slate-200 p-3 transition hover:border-teal-300 hover:bg-teal-50/40"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-semibold text-slate-950">{{ item.label }}</p>
                            <Badge :tone="item.tone">Open</Badge>
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ item.caption }}</p>
                    </RouterLink>

                    <EmptyState v-if="workQueue.length === 0" title="No blockers in the queue" description="Orders can continue through fulfillment without operator action." :icon="PackageCheck" />
                </div>
            </Card>
        </div>
    </div>
</template>
