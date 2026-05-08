<script setup lang="ts">
import { ClipboardList, Download, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import { dateLabel, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';

const store = usePortalStore();
const query = ref('');
const status = ref('all');

const orders = computed(() => store.orders.filter((order) => {
    const text = `${order.order_number} ${order.customer_name} ${order.shipping_service} ${order.items?.map((item) => item.item_name).join(' ')}`.toLowerCase();
    const matchesSearch = text.includes(query.value.toLowerCase());
    const matchesStatus = status.value === 'all' || order.status === status.value;

    return matchesSearch && matchesStatus;
}));
</script>

<template>
    <div class="grid gap-5">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">Orders List</h2>
                <p class="text-slate-600">Filter, inspect, export, and triage B2B print orders.</p>
            </div>
            <div class="flex gap-2">
                <RouterLink to="/orders/import"><Button variant="outline">Import CSV</Button></RouterLink>
                <RouterLink to="/orders/new"><Button>New Order</Button></RouterLink>
            </div>
        </div>

        <Card>
            <div class="mb-4 grid gap-3 md:grid-cols-[180px_1fr_auto]">
                <Select v-model="status" label="Status">
                    <option value="all">All statuses</option>
                    <option value="verified">Verified</option>
                    <option value="in_production">In production</option>
                    <option value="action_needed">Action needed</option>
                    <option value="shipped">Shipped</option>
                </Select>
                <Input v-model="query" label="Search" placeholder="Order, customer, SKU, product..." />
                <Button variant="outline" class="self-end">
                    <Download class="h-4 w-4" />
                    Export
                </Button>
            </div>

            <DataTable min-width="1040px">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Order Id</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Submitted</th>
                            <th class="px-4 py-3">Shipped</th>
                            <th class="px-4 py-3">Shipping Service</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Items</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="order in orders" :key="order.id" class="bg-white hover:bg-slate-50">
                            <td class="px-4 py-3 font-semibold text-teal-800">
                                <RouterLink :to="`/orders/${order.uuid}`">{{ order.order_number }}</RouterLink>
                            </td>
                            <td class="px-4 py-3"><Badge :tone="statusTone(order.status)">{{ order.status.replace('_', ' ') }}</Badge></td>
                            <td class="px-4 py-3 text-slate-600">{{ dateLabel(order.submitted_at) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ dateLabel(order.shipped_at) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ order.shipping_service ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ order.customer_name }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                <span class="line-clamp-2">{{ order.items?.map((item) => item.item_name).join(', ') }}</span>
                            </td>
                        </tr>
                    </tbody>
            </DataTable>

            <EmptyState
                v-if="orders.length === 0"
                class="mt-4"
                title="No orders match these filters"
                description="Adjust search terms or status filters to widen the list."
                :icon="ClipboardList"
            />

            <div class="mt-4 flex items-center justify-between text-sm text-slate-500">
                <span>{{ orders.length }} records shown</span>
                <span class="inline-flex items-center gap-2"><Search class="h-4 w-4" /> Client-side filter for this MVP</span>
            </div>
        </Card>
    </div>
</template>
