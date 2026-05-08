<script setup lang="ts">
import { AlertTriangle, ClipboardList, FileWarning, LifeBuoy, Map, PackageCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

import StatCard from '@/components/portal/StatCard.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import DataTable from '@/components/ui/Table.vue';
import { dateLabel, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';

const store = usePortalStore();

const latestOrders = computed(() => store.orders.slice(0, 5));
const workQueue = computed(() => [
    ...store.requiredActions.filter((action) => action.status === 'open').slice(0, 3).map((action) => ({
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

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Orders" :value="store.metrics.orders ?? 0" caption="Across all active statuses" :icon="ClipboardList" />
            <StatCard label="Action Needed" :value="store.metrics.actionNeeded ?? 0" caption="Blocked by customer data" :icon="AlertTriangle" />
            <StatCard label="Tickets" :value="store.metrics.tickets ?? 0" caption="Support requests" :icon="LifeBuoy" />
            <StatCard label="Required Actions" :value="store.metrics.requiredActions ?? 0" caption="Mapping and validation queue" :icon="Map" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.4fr_.9fr]">
            <Card>
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-950">Latest Orders</h3>
                        <p class="text-sm text-slate-500">Recent orders with status, service, and customer context.</p>
                    </div>
                    <RouterLink to="/orders">
                        <Button variant="outline" size="sm">View all</Button>
                    </RouterLink>
                </div>

                <DataTable min-width="760px">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Order</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Customer</th>
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
                                <td class="px-4 py-3 text-slate-600">{{ dateLabel(order.submitted_at) }}</td>
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
                    <FileWarning class="h-5 w-5 text-orange-600" />
                </div>

                <div class="grid gap-3">
                    <RouterLink
                        v-for="item in workQueue"
                        :key="item.label + item.caption"
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
