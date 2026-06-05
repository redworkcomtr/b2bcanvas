<script setup lang="ts">
import {
    AlertTriangle,
    ArrowDownUp,
    Boxes,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    ClipboardList,
    CreditCard,
    Download,
    Eye,
    FileWarning,
    Layers3,
    MoreHorizontal,
    PackageCheck,
    Plus,
    RefreshCcw,
    Save,
    Search,
    Send,
    ShieldAlert,
    MessageSquare,
    Truck,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';

import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import TableHeaderCell from '@/components/ui/TableHeaderCell.vue';
import { dateLabel, humanize, money, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';
import type { Order, SavedView } from '@/types/portal';

const store = usePortalStore();
const loading = ref(false);
const exporting = ref(false);
const previewLoading = ref(false);
const errorMessage = ref('');
const saveViewName = ref('');
const advancedFiltersOpen = ref(false);
const previewOpen = ref(false);
const selectedOrder = ref<Order | null>(null);
const selectedOrderIds = ref<number[]>([]);

const filters = reactive({
    q: '',
    status: 'all',
    date_from: '',
    date_to: '',
    sort: 'submitted_at',
    direction: 'desc',
    per_page: 25,
    page: 1,
});

const statusOptions = [
    'all',
    'draft',
    'validation_failed',
    'action_needed',
    'verified',
    'submitted',
    'in_production',
    'shipped',
    'closed',
    'cancelled',
];
const perPageOptions = [10, 25, 50, 100];
const defaultSortDirections: Record<string, 'asc' | 'desc'> = {
    order_number: 'asc',
    customer_name: 'asc',
    status: 'asc',
    submitted_at: 'desc',
    order_date: 'desc',
    shipped_at: 'desc',
};

const params = computed(() => ({
    q: filters.q,
    status: filters.status,
    date_from: filters.date_from,
    date_to: filters.date_to,
    sort: filters.sort,
    direction: filters.direction,
    per_page: Number(filters.per_page),
    page: filters.page,
}));

const meta = computed(() => store.ordersMeta);
const orders = computed(() => store.orderList);
const summary = computed(() => store.ordersSummary);

const activeFilterCount = computed(() => [
    filters.q,
    filters.status !== 'all' ? filters.status : '',
    filters.date_from,
    filters.date_to,
].filter(Boolean).length);

const selectedOrders = computed(() => orders.value.filter((order) => selectedOrderIds.value.includes(order.id)));

const allPageSelected = computed(() => orders.value.length > 0 && orders.value.every((order) => selectedOrderIds.value.includes(order.id)));

const criticalCount = computed(() => summaryCount('action_needed') + summaryCount('validation_failed'));

const workflowQueues = computed(() => [
    {
        label: 'Needs Review',
        status: 'action_needed',
        count: summaryCount('action_needed'),
        caption: 'Customer or production input',
        tone: 'warning',
        icon: AlertTriangle,
    },
    {
        label: 'Exceptions',
        status: 'validation_failed',
        count: summaryCount('validation_failed'),
        caption: 'Broken data or blocked flow',
        tone: 'danger',
        icon: ShieldAlert,
    },
    {
        label: 'Ready for Production',
        status: 'verified',
        count: summaryCount('verified'),
        caption: 'Verified and ready to queue',
        tone: 'success',
        icon: CheckCircle2,
    },
    {
        label: 'Production',
        status: 'in_production',
        count: summaryCount('in_production'),
        caption: 'Currently being fulfilled',
        tone: 'info',
        icon: Boxes,
    },
    {
        label: 'Ready / Submitted',
        status: 'submitted',
        count: summaryCount('submitted'),
        caption: 'Submitted and waiting',
        tone: 'neutral',
        icon: Layers3,
    },
    {
        label: 'Shipped',
        status: 'shipped',
        count: summaryCount('shipped'),
        caption: 'Fulfillment handoff complete',
        tone: 'success',
        icon: Truck,
    },
    {
        label: 'All Orders',
        status: 'all',
        count: summaryCount('all'),
        caption: 'Full operational ledger',
        tone: 'neutral',
        icon: ClipboardList,
    },
]);

const activeQueue = computed(() => workflowQueues.value.find((queue) => queue.status === filters.status) ?? workflowQueues.value[workflowQueues.value.length - 1]);

async function loadOrders(page = filters.page) {
    loading.value = true;
    errorMessage.value = '';
    filters.page = page;

    try {
        await store.fetchOrders(params.value);
        selectedOrderIds.value = selectedOrderIds.value.filter((id) => orders.value.some((order) => order.id === id));
    } catch {
        errorMessage.value = 'Orders could not be loaded.';
    } finally {
        loading.value = false;
    }
}

function summaryCount(status: string) {
    if (status === 'all') {
        return summary.value.total ?? store.metrics.orders ?? meta.value?.total ?? orders.value.length;
    }

    return summary.value[status] ?? store.metrics[`orders_${status}`] ?? 0;
}

function applySavedView(view: SavedView) {
    filters.q = view.filters?.q ?? '';
    filters.status = view.filters?.status ?? 'all';
    filters.date_from = view.filters?.date_from ?? '';
    filters.date_to = view.filters?.date_to ?? '';
    filters.sort = view.sort?.sort ?? 'submitted_at';
    filters.direction = view.sort?.direction ?? 'desc';
    void loadOrders(1);
}

function onSavedViewChange(event: Event) {
    const id = (event.target as HTMLSelectElement).value;
    const view = store.savedViews.find((item) => String(item.id) === id);
    if (view) {
        applySavedView(view);
    }
}

function setQueue(status: string) {
    filters.status = status;
    selectedOrderIds.value = [];
}

function requiredActionsFor(order: Order) {
    return order.requiredActions ?? order.required_actions ?? [];
}

function issuesFor(order: Order) {
    return order.issues ?? [];
}

function activeRequiredAction(order: Order) {
    return requiredActionsFor(order).find((action) => ['open', 'in_progress', 'escalated'].includes(action.status));
}

function unresolvedIssue(order: Order) {
    return issuesFor(order).find((issue) => !['resolved', 'closed'].includes(issue.status));
}

function rowSignal(order: Order) {
    const action = activeRequiredAction(order);
    const issue = unresolvedIssue(order);

    if (action) {
        return {
            label: action.priority === 'urgent' ? 'Urgent action' : 'Action required',
            reason: action.title,
            action: action.type === 'product_mapping_required' ? 'Map SKU' : 'Resolve',
            tone: action.priority === 'urgent' || action.status === 'escalated' ? 'danger' : 'warning',
            icon: action.type === 'product_mapping_required' ? Layers3 : FileWarning,
        };
    }

    if (order.status === 'validation_failed') {
        return {
            label: 'Validation failed',
            reason: 'Fix order data before production',
            action: 'Review',
            tone: 'danger',
            icon: ShieldAlert,
        };
    }

    if (order.payment_status === 'unpaid') {
        return {
            label: 'Payment hold',
            reason: 'Payment is required before fulfillment',
            action: 'Collect',
            tone: 'warning',
            icon: CreditCard,
        };
    }

    if (issue) {
        return {
            label: issue.type === 'claim' ? 'Claim open' : 'Support open',
            reason: issue.description,
            action: issue.unread_notes_count > 0 ? 'Reply' : 'Track',
            tone: issue.priority === 'urgent' || issue.priority === 'high' ? 'warning' : 'info',
            icon: issue.unread_notes_count > 0 ? MessageSquare : Eye,
        };
    }

    if (order.status === 'verified') {
        return {
            label: 'Ready for production',
            reason: 'Order is verified and can enter the queue',
            action: 'Send',
            tone: 'success',
            icon: Send,
        };
    }

    if (order.status === 'submitted') {
        return {
            label: 'Submitted',
            reason: 'Waiting for production intake',
            action: 'Queue',
            tone: 'neutral',
            icon: Layers3,
        };
    }

    if (order.status === 'in_production') {
        return {
            label: 'In production',
            reason: 'Track production progress and shipment',
            action: 'Track',
            tone: 'info',
            icon: Eye,
        };
    }

    if (order.status === 'shipped') {
        return {
            label: 'Shipped',
            reason: order.tracking_number ? `Tracking ${order.tracking_number}` : 'Fulfillment is complete',
            action: 'View',
            tone: 'success',
            icon: Truck,
        };
    }

    if (order.status === 'draft') {
        return {
            label: 'Draft',
            reason: 'Complete setup before submission',
            action: 'Finish',
            tone: 'neutral',
            icon: ClipboardList,
        };
    }

    return {
        label: humanize(order.status),
        reason: 'No blocking action detected',
        action: 'Open',
        tone: 'neutral',
        icon: Eye,
    };
}

function productionStage(order: Order) {
    if (['closed', 'cancelled'].includes(order.status)) {
        return { label: humanize(order.status), progress: 100, tone: order.status === 'cancelled' ? 'danger' : 'neutral' };
    }

    if (order.status === 'shipped') {
        return { label: 'Fulfilled', progress: 100, tone: 'success' };
    }

    if (order.status === 'in_production') {
        return { label: 'Production', progress: 68, tone: 'info' };
    }

    if (order.status === 'submitted') {
        return { label: 'Queued', progress: 46, tone: 'neutral' };
    }

    if (order.status === 'verified') {
        return { label: 'Ready', progress: 32, tone: 'success' };
    }

    if (['action_needed', 'validation_failed'].includes(order.status)) {
        return { label: 'Blocked', progress: 14, tone: 'warning' };
    }

    return { label: 'Intake', progress: 8, tone: 'neutral' };
}

function itemSummary(order: Order) {
    const items = order.items ?? [];
    const quantity = items.reduce((sum, item) => sum + Number(item.quantity ?? 0), 0);
    const first = items[0]?.item_name ?? 'No items';
    const suffix = items.length > 1 ? ` +${items.length - 1}` : '';

    return `${quantity || items.length} units · ${first}${suffix}`;
}

function orderAgeLabel(order: Order) {
    const value = order.submitted_at ?? order.order_date;
    if (!value) {
        return 'Not submitted';
    }

    const created = new Date(value).getTime();
    if (!Number.isFinite(created)) {
        return 'Date unavailable';
    }

    const days = Math.max(0, Math.floor((Date.now() - created) / 86400000));
    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return '1 day open';
    }

    return `${days} days open`;
}

function ownerLabel(order: Order) {
    const action = activeRequiredAction(order);
    if (action?.assigned_to?.name || action?.assignedTo?.name) {
        return action.assigned_to?.name ?? action.assignedTo?.name;
    }

    const issue = unresolvedIssue(order);
    if (issue?.assigned_to?.name || issue?.assignedTo?.name) {
        return issue.assigned_to?.name ?? issue.assignedTo?.name;
    }

    return 'Unassigned';
}

function toggleOrderSelection(order: Order) {
    selectedOrderIds.value = selectedOrderIds.value.includes(order.id)
        ? selectedOrderIds.value.filter((id) => id !== order.id)
        : [...selectedOrderIds.value, order.id];
}

function togglePageSelection() {
    selectedOrderIds.value = allPageSelected.value ? [] : orders.value.map((order) => order.id);
}

function setSort(sortKey: string) {
    if (filters.sort === sortKey) {
        filters.direction = filters.direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort = sortKey;
        filters.direction = defaultSortDirections[sortKey] ?? 'asc';
    }

    selectedOrderIds.value = [];
}

function goToPage(page: number) {
    if (loading.value) {
        return;
    }

    const lastPage = meta.value?.last_page ?? page;
    const nextPage = Math.min(Math.max(page, 1), Math.max(lastPage, 1));
    void loadOrders(nextPage);
}

function setPerPage(event: Event) {
    filters.per_page = Number((event.target as HTMLSelectElement).value);
    selectedOrderIds.value = [];
}

function resetFilters() {
    filters.q = '';
    filters.status = 'all';
    filters.date_from = '';
    filters.date_to = '';
    filters.sort = 'submitted_at';
    filters.direction = 'desc';
    filters.per_page = 25;
    selectedOrderIds.value = [];
    void loadOrders(1);
}

async function saveCurrentView() {
    if (!saveViewName.value.trim()) {
        errorMessage.value = 'Saved view name is required.';
        return;
    }

    await store.saveOrderView({
        name: saveViewName.value.trim(),
        filters: {
            q: filters.q,
            status: filters.status,
            date_from: filters.date_from,
            date_to: filters.date_to,
        },
        sort: {
            sort: filters.sort,
            direction: filters.direction,
        },
    });
    saveViewName.value = '';
}

async function exportOrders() {
    exporting.value = true;
    try {
        const blob = await store.exportOrders(params.value);
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'orders-export.csv';
        link.click();
        URL.revokeObjectURL(url);
    } finally {
        exporting.value = false;
    }
}

async function openPreview(order: Order) {
    selectedOrder.value = order;
    previewOpen.value = true;
    previewLoading.value = true;

    try {
        const fresh = await store.fetchOrder(order.uuid);
        if (selectedOrder.value?.uuid === order.uuid) {
            selectedOrder.value = fresh;
        }
    } finally {
        previewLoading.value = false;
    }
}

function openFirstSelected() {
    const order = selectedOrders.value[0];
    if (order) {
        void openPreview(order);
    }
}

function closePreview() {
    previewOpen.value = false;
    selectedOrder.value = null;
}

watch(() => [filters.status, filters.date_from, filters.date_to, filters.sort, filters.direction, filters.per_page], () => {
    void loadOrders(1);
});

onMounted(() => {
    void loadOrders();
});
</script>

<template>
    <div class="grid gap-4">
        <section class="flex flex-col gap-4 border-b border-zinc-200/80 pb-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <Badge :tone="criticalCount > 0 ? 'warning' : 'success'">
                        {{ criticalCount }} need attention
                    </Badge>
                    <span class="text-xs font-medium text-zinc-500">
                        {{ meta?.total ?? orders.length }} records · {{ activeQueue.label }}
                    </span>
                </div>
                <h2 class="text-2xl font-semibold leading-tight text-zinc-950">Orders workspace</h2>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-zinc-500">
                    Manage production queues, exceptions, shipments, and customer blockers without losing list context.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <RouterLink to="/orders/import">
                    <Button variant="outline" size="sm">
                        <Upload class="h-4 w-4" />
                        Import
                    </Button>
                </RouterLink>
                <Button variant="outline" size="sm" :disabled="exporting" @click="exportOrders">
                    <Download class="h-4 w-4" />
                    Export
                </Button>
                <RouterLink to="/orders/new">
                    <Button size="sm">
                        <Plus class="h-4 w-4" />
                        New Order
                    </Button>
                </RouterLink>
            </div>
        </section>

        <section class="grid gap-2 xl:grid-cols-7">
            <button
                v-for="queue in workflowQueues"
                :key="queue.status"
                :class="[
                    'focus-ring min-w-0 rounded-lg border bg-white p-3 text-left transition-colors hover:border-zinc-300 hover:bg-zinc-50',
                    filters.status === queue.status ? 'border-zinc-950 shadow-[inset_0_0_0_1px_#18181b]' : 'border-zinc-200/80',
                ]"
                @click="setQueue(queue.status)"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <component
                        :is="queue.icon"
                        :class="[
                            'h-4 w-4',
                            queue.tone === 'warning' ? 'text-amber-600' : '',
                            queue.tone === 'danger' ? 'text-red-600' : '',
                            queue.tone === 'success' ? 'text-emerald-600' : '',
                            queue.tone === 'info' ? 'text-blue-600' : '',
                            queue.tone === 'neutral' ? 'text-zinc-500' : '',
                        ]"
                    />
                    <span class="text-lg font-semibold leading-none text-zinc-950">{{ queue.count }}</span>
                </div>
                <p class="truncate text-sm font-semibold text-zinc-950">{{ queue.label }}</p>
                <p class="mt-1 line-clamp-2 text-xs leading-5 text-zinc-500">{{ queue.caption }}</p>
            </button>
        </section>

        <section class="rounded-lg border border-zinc-200/80 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
            <div class="grid gap-3 p-3 lg:grid-cols-[minmax(260px,1fr)_180px_180px_auto] lg:items-end">
                <Input v-model="filters.q" label="Search workspace" placeholder="Order, customer, SKU, product..." @keyup.enter="loadOrders(1)">
                    <template #prefix>
                        <Search class="h-4 w-4" />
                    </template>
                </Input>
                <Input v-model="filters.date_from" label="Submitted from" type="date" />
                <Input v-model="filters.date_to" label="Submitted to" type="date" />
                <div class="flex flex-wrap items-end gap-2">
                    <Button class="flex-1 lg:flex-none" size="sm" @click="loadOrders(1)">
                        <Search class="h-4 w-4" />
                        Search
                    </Button>
                    <Button variant="outline" size="sm" @click="advancedFiltersOpen = !advancedFiltersOpen">
                        <MoreHorizontal class="h-4 w-4" />
                        More
                    </Button>
                    <Button v-if="activeFilterCount" variant="ghost" size="sm" @click="resetFilters">
                        <X class="h-4 w-4" />
                        Reset
                    </Button>
                </div>
            </div>

            <div v-if="advancedFiltersOpen" class="grid gap-3 border-t border-zinc-200/80 p-3 lg:grid-cols-[180px_180px_220px_1fr_auto] lg:items-end">
                <Select v-model="filters.sort" label="Sort by">
                    <option value="submitted_at">Submitted</option>
                    <option value="order_date">Order date</option>
                    <option value="shipped_at">Shipped</option>
                    <option value="order_number">Order ID</option>
                    <option value="customer_name">Customer</option>
                    <option value="status">Status</option>
                </Select>
                <Select v-model="filters.direction" label="Direction">
                    <option value="desc">Desc</option>
                    <option value="asc">Asc</option>
                </Select>
                <Select label="Saved view" @change="onSavedViewChange">
                    <option value="">Choose saved view</option>
                    <option v-for="view in store.savedViews" :key="view.id" :value="view.id">{{ view.name }}</option>
                </Select>
                <Input v-model="saveViewName" label="Save current view" placeholder="Production watchlist" />
                <Button class="w-full lg:w-auto" variant="outline" size="sm" @click="saveCurrentView">
                    <Save class="h-4 w-4" />
                    Save
                </Button>
            </div>
        </section>

        <Alert v-if="errorMessage" tone="danger" title="Orders workflow warning" :description="errorMessage" />

        <section
            v-if="selectedOrders.length"
            class="sticky top-0 z-20 flex flex-col gap-3 rounded-lg border border-zinc-300 bg-zinc-950 px-4 py-3 text-white shadow-[0_14px_30px_-20px_rgba(15,23,42,0.6)] sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-sm font-semibold">{{ selectedOrders.length }} selected</p>
                <p class="text-xs text-zinc-300">Review, export, or clear this working set.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="secondary" size="sm" @click="openFirstSelected">
                    <Eye class="h-4 w-4" />
                    Review first
                </Button>
                <Button variant="outline" size="sm" :disabled="exporting" @click="exportOrders">
                    <Download class="h-4 w-4" />
                    Export view
                </Button>
                <Button variant="ghost" size="sm" class="text-white hover:bg-white/10 hover:text-white" @click="selectedOrderIds = []">
                    <X class="h-4 w-4" />
                    Clear
                </Button>
            </div>
        </section>

        <section class="grid gap-3">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-950">Command table</h3>
                    <p class="text-sm text-zinc-500">
                        {{ activeQueue.label }} · sorted by {{ filters.sort.replace(/_/g, ' ') }} {{ filters.direction }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-medium text-zinc-500">
                    <span class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-white px-2.5 py-1">
                        <ArrowDownUp class="h-3.5 w-3.5" />
                        {{ filters.per_page }} per page
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-white px-2.5 py-1">
                        <RefreshCcw class="h-3.5 w-3.5" />
                        Server filtered
                    </span>
                </div>
            </div>

            <DataTable min-width="1240px" sticky-actions>
                <thead>
                    <tr>
                        <TableHeaderCell class="w-10">
                            <input
                                class="h-4 w-4 rounded border-zinc-300 accent-black"
                                type="checkbox"
                                :checked="allPageSelected"
                                :aria-label="allPageSelected ? 'Clear page selection' : 'Select page orders'"
                                @change="togglePageSelection"
                            >
                        </TableHeaderCell>
                        <TableHeaderCell label="Work item" sort-key="order_number" :sort="filters.sort" :direction="filters.direction" @sort="setSort" />
                        <TableHeaderCell label="Signal / next action" sort-key="status" :sort="filters.sort" :direction="filters.direction" @sort="setSort" />
                        <TableHeaderCell label="Production" sort-key="status" :sort="filters.sort" :direction="filters.direction" @sort="setSort" />
                        <TableHeaderCell label="SLA" sort-key="submitted_at" :sort="filters.sort" :direction="filters.direction" @sort="setSort" />
                        <TableHeaderCell label="Shipping" sort-key="shipped_at" :sort="filters.sort" :direction="filters.direction" @sort="setSort" />
                        <TableHeaderCell label="Owner" sort-key="customer_name" :sort="filters.sort" :direction="filters.direction" @sort="setSort" />
                        <TableHeaderCell label="Action" align="right" sticky />
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr
                        v-for="order in orders"
                        :key="order.id"
                        class="group cursor-pointer"
                        @click="openPreview(order)"
                    >
                        <td class="px-4 py-3.5" @click.stop>
                            <input
                                class="h-4 w-4 rounded border-zinc-300 accent-black"
                                type="checkbox"
                                :checked="selectedOrderIds.includes(order.id)"
                                :aria-label="`Select ${order.order_number}`"
                                @change="toggleOrderSelection(order)"
                            >
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex min-w-0 items-start gap-3">
                                <div
                                    :class="[
                                        'mt-1 h-9 w-1 shrink-0 rounded-full',
                                        rowSignal(order).tone === 'danger' ? 'bg-red-500' : '',
                                        rowSignal(order).tone === 'warning' ? 'bg-amber-500' : '',
                                        rowSignal(order).tone === 'success' ? 'bg-emerald-500' : '',
                                        rowSignal(order).tone === 'info' ? 'bg-blue-500' : '',
                                        rowSignal(order).tone === 'neutral' ? 'bg-zinc-300' : '',
                                    ]"
                                />
                                <div class="min-w-0">
                                    <p class="font-semibold text-zinc-950 group-hover:text-zinc-700">{{ order.order_number }}</p>
                                    <p class="mt-0.5 truncate text-sm text-zinc-600">{{ order.customer_name }}</p>
                                    <p class="mt-1 line-clamp-1 text-xs text-zinc-500">{{ itemSummary(order) }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge :tone="rowSignal(order).tone">{{ rowSignal(order).label }}</Badge>
                                <Badge :tone="statusTone(order.status)">{{ humanize(order.status) }}</Badge>
                            </div>
                            <p class="mt-2 line-clamp-1 max-w-sm text-sm font-medium text-zinc-700">{{ rowSignal(order).reason }}</p>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="min-w-40">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-zinc-800">{{ productionStage(order).label }}</span>
                                    <span class="text-xs text-zinc-500">{{ productionStage(order).progress }}%</span>
                                </div>
                                <div class="mt-2 h-1.5 rounded-full bg-zinc-100">
                                    <div
                                        :class="[
                                            'h-1.5 rounded-full',
                                            productionStage(order).tone === 'danger' ? 'bg-red-500' : '',
                                            productionStage(order).tone === 'warning' ? 'bg-amber-500' : '',
                                            productionStage(order).tone === 'success' ? 'bg-emerald-500' : '',
                                            productionStage(order).tone === 'info' ? 'bg-blue-500' : '',
                                            productionStage(order).tone === 'neutral' ? 'bg-zinc-400' : '',
                                        ]"
                                        :style="{ width: `${productionStage(order).progress}%` }"
                                    />
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3.5">
                            <p class="text-sm font-semibold text-zinc-800">{{ orderAgeLabel(order) }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ dateLabel(order.submitted_at ?? order.order_date) }}</p>
                        </td>
                        <td class="px-4 py-3.5">
                            <p class="text-sm font-medium text-zinc-800">{{ order.shipping_service ?? 'Not selected' }}</p>
                            <p class="mt-1 line-clamp-1 text-xs text-zinc-500">{{ order.tracking_number ?? 'No tracking yet' }}</p>
                        </td>
                        <td class="px-4 py-3.5">
                            <p class="text-sm font-medium text-zinc-800">{{ ownerLabel(order) }}</p>
                            <p class="mt-1 text-xs text-zinc-500">{{ money(order.totals?.subtotal_cents ?? 0, order.totals?.currency ?? 'USD') }}</p>
                        </td>
                        <td class="px-4 py-3.5 text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                class="ml-auto h-8 min-w-[5.75rem] justify-center border-zinc-200 bg-white px-2.5 text-xs font-medium text-zinc-700 shadow-none hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-950"
                                @click.stop="openPreview(order)"
                            >
                                <component :is="rowSignal(order).icon" class="h-3.5 w-3.5 text-current" />
                                {{ rowSignal(order).action }}
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </DataTable>

            <EmptyState
                v-if="!loading && orders.length === 0"
                title="No orders in this queue"
                description="Switch work queues or clear filters to widen the operational list."
                :icon="PackageCheck"
            />

            <div class="flex flex-col justify-between gap-3 rounded-lg border border-zinc-200/80 bg-white px-4 py-3 text-sm text-zinc-500 lg:flex-row lg:items-center">
                <div class="flex flex-wrap items-center gap-3">
                    <span>
                        {{ meta?.from ?? 0 }}-{{ meta?.to ?? 0 }} of {{ meta?.total ?? orders.length }} records
                    </span>
                    <span class="hidden h-4 w-px bg-zinc-200 sm:block" />
                    <span>Page {{ meta?.current_page ?? 1 }} of {{ meta?.last_page ?? 1 }}</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="inline-flex h-8 items-center gap-2 rounded-md border border-zinc-200 bg-white px-2.5 text-xs font-medium text-zinc-600">
                        Rows
                        <select
                            class="h-6 rounded border-0 bg-transparent font-sans text-xs font-semibold text-zinc-950 outline-none"
                            :value="filters.per_page"
                            :disabled="loading"
                            @change="setPerPage"
                        >
                            <option v-for="option in perPageOptions" :key="option" :value="option">{{ option }}</option>
                        </select>
                    </label>
                    <Button variant="outline" size="sm" :disabled="loading || !meta || meta.current_page <= 1" @click="goToPage(1)">
                        <ChevronsLeft class="h-4 w-4" />
                        First
                    </Button>
                    <Button variant="outline" size="sm" :disabled="loading || !meta || meta.current_page <= 1" @click="goToPage((meta?.current_page ?? 1) - 1)">
                        <ChevronLeft class="h-4 w-4" />
                        Prev
                    </Button>
                    <Button variant="outline" size="sm" :disabled="loading || !meta || meta.current_page >= meta.last_page" @click="goToPage((meta?.current_page ?? 1) + 1)">
                        Next
                        <ChevronRight class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" :disabled="loading || !meta || meta.current_page >= meta.last_page" @click="goToPage(meta?.last_page ?? 1)">
                        Last
                        <ChevronsRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </section>

        <Teleport to="body">
            <div v-if="previewOpen && selectedOrder" class="fixed inset-0 z-50 bg-black/20" @click.self="closePreview">
                <aside class="sidebar-scroll fixed right-0 top-0 h-full w-[min(620px,94vw)] overflow-auto border-l border-zinc-200 bg-white shadow-[0_18px_50px_-24px_rgba(15,23,42,0.55)]">
                    <header class="sticky top-0 z-10 border-b border-zinc-200/80 bg-white px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <Badge :tone="rowSignal(selectedOrder).tone">{{ rowSignal(selectedOrder).label }}</Badge>
                                    <span v-if="previewLoading" class="text-xs font-medium text-zinc-500">Refreshing...</span>
                                </div>
                                <h3 class="truncate text-xl font-semibold text-zinc-950">{{ selectedOrder.order_number }}</h3>
                                <p class="mt-1 truncate text-sm text-zinc-500">{{ selectedOrder.customer_name }}</p>
                            </div>
                            <Button variant="ghost" size="icon" aria-label="Close order preview" @click="closePreview">
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                    </header>

                    <div class="grid gap-4 p-5">
                        <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
                            <div class="flex items-start gap-3">
                                <FileWarning
                                    :class="[
                                        'mt-0.5 h-5 w-5 shrink-0',
                                        rowSignal(selectedOrder).tone === 'danger' ? 'text-red-600' : '',
                                        rowSignal(selectedOrder).tone === 'warning' ? 'text-amber-600' : '',
                                        rowSignal(selectedOrder).tone === 'success' ? 'text-emerald-600' : '',
                                        rowSignal(selectedOrder).tone === 'info' ? 'text-blue-600' : '',
                                        rowSignal(selectedOrder).tone === 'neutral' ? 'text-zinc-500' : '',
                                    ]"
                                />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-zinc-950">{{ rowSignal(selectedOrder).reason }}</p>
                                    <p class="mt-1 text-sm leading-6 text-zinc-600">
                                        Recommended next step: {{ rowSignal(selectedOrder).action }}.
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <RouterLink :to="`/orders/${selectedOrder.uuid}`">
                                    <Button size="sm">
                                        <Eye class="h-4 w-4" />
                                        Open full order
                                    </Button>
                                </RouterLink>
                                <Button variant="outline" size="sm" @click="selectedOrderIds = selectedOrderIds.includes(selectedOrder.id) ? selectedOrderIds : [...selectedOrderIds, selectedOrder.id]">
                                    <Plus class="h-4 w-4" />
                                    Add to working set
                                </Button>
                            </div>
                        </section>

                        <section class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-lg border border-zinc-200 bg-white p-3">
                                <p class="text-xs font-semibold uppercase text-zinc-500">Production</p>
                                <p class="mt-2 text-sm font-semibold text-zinc-950">{{ productionStage(selectedOrder).label }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ productionStage(selectedOrder).progress }}% through flow</p>
                            </div>
                            <div class="rounded-lg border border-zinc-200 bg-white p-3">
                                <p class="text-xs font-semibold uppercase text-zinc-500">SLA</p>
                                <p class="mt-2 text-sm font-semibold text-zinc-950">{{ orderAgeLabel(selectedOrder) }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ dateLabel(selectedOrder.submitted_at ?? selectedOrder.order_date) }}</p>
                            </div>
                            <div class="rounded-lg border border-zinc-200 bg-white p-3">
                                <p class="text-xs font-semibold uppercase text-zinc-500">Value</p>
                                <p class="mt-2 text-sm font-semibold text-zinc-950">{{ money(selectedOrder.totals?.subtotal_cents ?? 0, selectedOrder.totals?.currency ?? 'USD') }}</p>
                                <p class="mt-1 text-xs text-zinc-500">{{ selectedOrder.payment_status ?? 'payment unknown' }}</p>
                            </div>
                        </section>

                        <section class="rounded-lg border border-zinc-200 bg-white">
                            <div class="border-b border-zinc-200/80 px-4 py-3">
                                <h4 class="text-sm font-semibold text-zinc-950">Items</h4>
                            </div>
                            <div class="grid divide-y divide-zinc-100">
                                <div v-for="item in selectedOrder.items" :key="item.id" class="px-4 py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="line-clamp-1 text-sm font-semibold text-zinc-950">{{ item.item_name }}</p>
                                            <p class="mt-1 text-xs text-zinc-500">{{ item.item_sku ?? item.product_code ?? 'No SKU' }}</p>
                                        </div>
                                        <Badge tone="neutral">Qty {{ item.quantity }}</Badge>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="grid gap-4 lg:grid-cols-2">
                            <div class="rounded-lg border border-zinc-200 bg-white">
                                <div class="border-b border-zinc-200/80 px-4 py-3">
                                    <h4 class="text-sm font-semibold text-zinc-950">Required actions</h4>
                                </div>
                                <div class="grid gap-3 p-4">
                                    <div v-for="action in requiredActionsFor(selectedOrder)" :key="action.id" class="rounded-md border border-zinc-200 bg-zinc-50 p-3">
                                        <div class="mb-2 flex flex-wrap gap-2">
                                            <Badge :tone="statusTone(action.status)">{{ humanize(action.status) }}</Badge>
                                            <Badge :tone="action.priority === 'urgent' ? 'danger' : action.priority === 'high' ? 'warning' : 'neutral'">{{ action.priority }}</Badge>
                                        </div>
                                        <p class="text-sm font-semibold text-zinc-950">{{ action.title }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-zinc-500">{{ action.description }}</p>
                                    </div>
                                    <p v-if="requiredActionsFor(selectedOrder).length === 0" class="text-sm text-zinc-500">No open required actions.</p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-zinc-200 bg-white">
                                <div class="border-b border-zinc-200/80 px-4 py-3">
                                    <h4 class="text-sm font-semibold text-zinc-950">Support / claims</h4>
                                </div>
                                <div class="grid gap-3 p-4">
                                    <div v-for="issue in issuesFor(selectedOrder)" :key="issue.id" class="rounded-md border border-zinc-200 bg-zinc-50 p-3">
                                        <div class="mb-2 flex flex-wrap gap-2">
                                            <Badge :tone="issue.type === 'claim' ? 'warning' : 'info'">{{ issue.type }}</Badge>
                                            <Badge :tone="statusTone(issue.status)">{{ humanize(issue.status) }}</Badge>
                                        </div>
                                        <p class="line-clamp-2 text-sm font-semibold text-zinc-950">{{ issue.description }}</p>
                                        <p class="mt-1 text-xs text-zinc-500">{{ issue.unread_notes_count }} unread notes</p>
                                    </div>
                                    <p v-if="issuesFor(selectedOrder).length === 0" class="text-sm text-zinc-500">No linked tickets or claims.</p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-lg border border-zinc-200 bg-white">
                            <div class="border-b border-zinc-200/80 px-4 py-3">
                                <h4 class="text-sm font-semibold text-zinc-950">Fulfillment</h4>
                            </div>
                            <div class="grid gap-3 p-4 text-sm">
                                <div class="flex justify-between gap-4">
                                    <span class="text-zinc-500">Shipping service</span>
                                    <span class="font-medium text-zinc-950">{{ selectedOrder.shipping_service ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <span class="text-zinc-500">Tracking</span>
                                    <span class="font-medium text-zinc-950">{{ selectedOrder.tracking_number ?? '-' }}</span>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <span class="text-zinc-500">Shipped</span>
                                    <span class="font-medium text-zinc-950">{{ dateLabel(selectedOrder.shipped_at) }}</span>
                                </div>
                            </div>
                        </section>
                    </div>
                </aside>
            </div>
        </Teleport>
    </div>
</template>
