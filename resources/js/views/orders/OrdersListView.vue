<script setup lang="ts">
import { ArrowDownUp, ChevronLeft, ChevronRight, ClipboardList, Download, Eye, Filter, Save, Search } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';

import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import { dateLabel, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';
import type { Order, SavedView } from '@/types/portal';

const store = usePortalStore();
const loading = ref(false);
const exporting = ref(false);
const errorMessage = ref('');
const saveViewName = ref('');

const filters = reactive({
    q: '',
    status: 'all',
    date_from: '',
    date_to: '',
    sort: 'submitted_at',
    direction: 'desc',
    per_page: 10,
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

const params = computed(() => ({
    q: filters.q,
    status: filters.status,
    date_from: filters.date_from,
    date_to: filters.date_to,
    sort: filters.sort,
    direction: filters.direction,
    per_page: filters.per_page,
    page: filters.page,
}));

const meta = computed(() => store.ordersMeta);
const orders = computed(() => store.orderList);
const summary = computed(() => store.ordersSummary);

async function loadOrders(page = filters.page) {
    loading.value = true;
    errorMessage.value = '';
    filters.page = page;

    try {
        await store.fetchOrders(params.value);
    } catch {
        errorMessage.value = 'Orders could not be loaded.';
    } finally {
        loading.value = false;
    }
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

function requiredActionsFor(order: Order) {
    return order.requiredActions ?? order.required_actions ?? [];
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

watch(() => [filters.status, filters.date_from, filters.date_to, filters.sort, filters.direction, filters.per_page], () => {
    void loadOrders(1);
});

onMounted(() => {
    void loadOrders();
});
</script>

<template>
    <div class="grid gap-5">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">Orders List</h2>
                <p class="text-slate-600">Server-side filtered order operations with saved views, lifecycle context, and CSV export.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <RouterLink to="/orders/import"><Button variant="outline">Import CSV</Button></RouterLink>
                <RouterLink to="/orders/new"><Button>New Order</Button></RouterLink>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-5">
            <Card class="p-4">
                <p class="text-sm text-slate-500">Total</p>
                <p class="mt-1 text-2xl font-bold text-slate-950">{{ summary.total ?? store.metrics.orders ?? 0 }}</p>
            </Card>
            <Card class="p-4">
                <p class="text-sm text-slate-500">Action Needed</p>
                <p class="mt-1 text-2xl font-bold text-amber-700">{{ summary.action_needed ?? 0 }}</p>
            </Card>
            <Card class="p-4">
                <p class="text-sm text-slate-500">Verified</p>
                <p class="mt-1 text-2xl font-bold text-teal-700">{{ summary.verified ?? 0 }}</p>
            </Card>
            <Card class="p-4">
                <p class="text-sm text-slate-500">In Production</p>
                <p class="mt-1 text-2xl font-bold text-blue-700">{{ summary.in_production ?? 0 }}</p>
            </Card>
            <Card class="p-4">
                <p class="text-sm text-slate-500">Shipped</p>
                <p class="mt-1 text-2xl font-bold text-emerald-700">{{ summary.shipped ?? 0 }}</p>
            </Card>
        </div>

        <Card>
            <div class="mb-4 grid gap-3 xl:grid-cols-[170px_1fr_140px_140px_160px_130px_auto]">
                <Select v-model="filters.status" label="Status">
                    <option v-for="option in statusOptions" :key="option" :value="option">{{ option.replace('_', ' ') }}</option>
                </Select>
                <Input v-model="filters.q" label="Search" placeholder="Order, customer, SKU, product..." @keyup.enter="loadOrders(1)" />
                <Input v-model="filters.date_from" label="From" type="date" />
                <Input v-model="filters.date_to" label="To" type="date" />
                <Select v-model="filters.sort" label="Sort">
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
                <div class="flex items-end gap-2">
                    <Button variant="outline" @click="loadOrders(1)"><Search class="h-4 w-4" /> Apply</Button>
                    <Button variant="outline" :disabled="exporting" @click="exportOrders">
                        <Download class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <div class="mb-4 grid gap-3 lg:grid-cols-[240px_1fr_auto]">
                <Select label="Saved views" @change="onSavedViewChange">
                    <option value="">Choose saved view</option>
                    <option v-for="view in store.savedViews" :key="view.id" :value="view.id">{{ view.name }}</option>
                </Select>
                <Input v-model="saveViewName" label="Save current view" placeholder="Production watchlist" />
                <Button class="self-end" variant="outline" @click="saveCurrentView"><Save class="h-4 w-4" /> Save view</Button>
            </div>

            <Alert v-if="errorMessage" tone="danger" title="Orders workflow warning" :description="errorMessage" class="mb-4" />

            <DataTable min-width="1120px">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3">Shipped</th>
                        <th class="px-4 py-3">Shipping</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Items</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="order in orders" :key="order.id" class="bg-white hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <RouterLink class="font-semibold text-teal-800" :to="`/orders/${order.uuid}`">{{ order.order_number }}</RouterLink>
                            <p v-if="requiredActionsFor(order).length" class="mt-1 text-xs font-semibold text-amber-700">{{ requiredActionsFor(order).length }} action</p>
                        </td>
                        <td class="px-4 py-3"><Badge :tone="statusTone(order.status)">{{ order.status.replace('_', ' ') }}</Badge></td>
                        <td class="px-4 py-3 text-slate-600">{{ dateLabel(order.submitted_at) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ dateLabel(order.shipped_at) }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            <p>{{ order.shipping_service ?? '-' }}</p>
                            <p v-if="order.tracking_number" class="text-xs text-slate-500">{{ order.tracking_number }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ order.customer_name }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            <span class="line-clamp-2">{{ order.items?.map((item) => item.item_name).join(', ') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <RouterLink :to="`/orders/${order.uuid}`">
                                <Button variant="ghost" size="icon" aria-label="Open order"><Eye class="h-4 w-4" /></Button>
                            </RouterLink>
                        </td>
                    </tr>
                </tbody>
            </DataTable>

            <EmptyState
                v-if="!loading && orders.length === 0"
                class="mt-4"
                title="No orders match these filters"
                description="Adjust search terms, dates, or status filters to widen the operational list."
                :icon="ClipboardList"
            />

            <div class="mt-4 flex flex-col justify-between gap-3 text-sm text-slate-500 md:flex-row md:items-center">
                <span>
                    {{ meta?.from ?? 0 }}-{{ meta?.to ?? 0 }} of {{ meta?.total ?? orders.length }} records
                </span>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-2"><Filter class="h-4 w-4" /> Server-side filters</span>
                    <span class="inline-flex items-center gap-2"><ArrowDownUp class="h-4 w-4" /> {{ filters.sort }} {{ filters.direction }}</span>
                    <Button variant="outline" size="sm" :disabled="loading || !meta || meta.current_page <= 1" @click="loadOrders((meta?.current_page ?? 1) - 1)">
                        <ChevronLeft class="h-4 w-4" /> Prev
                    </Button>
                    <Button variant="outline" size="sm" :disabled="loading || !meta || meta.current_page >= meta.last_page" @click="loadOrders((meta?.current_page ?? 1) + 1)">
                        Next <ChevronRight class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </Card>
    </div>
</template>
