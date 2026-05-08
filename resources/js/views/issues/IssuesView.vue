<script setup lang="ts">
import { MessageSquarePlus } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import Tabs from '@/components/ui/Tabs.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { dateLabel, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';

const props = defineProps<{ mode: 'tickets' | 'claims' | 'actions' }>();
const store = usePortalStore();
const status = ref('all');
const orderNumber = ref('');
const form = reactive({
    order_id: '',
    request_type: props.mode === 'claims' ? 'Credit' : 'Support',
    reason: 'Never Received',
    description: '',
    name: store.user?.name ?? '',
    email: store.user?.email ?? '',
    phone: '',
});

const statusTabs = [
    { label: 'All', value: 'all' },
    { label: 'Open', value: 'open' },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Closed', value: 'closed' },
];

const title = computed(() => ({
    tickets: 'Support Tickets',
    claims: 'Claims',
    actions: 'Required Actions',
}[props.mode]));

const issueRows = computed(() => {
    if (props.mode === 'actions') {
        return [];
    }

    const type = props.mode === 'tickets' ? 'ticket' : 'claim';
    return store.issues.filter((issue) => {
        const order = issue.order?.order_number ?? '';
        return issue.type === type
            && (status.value === 'all' || issue.status === status.value)
            && order.toLowerCase().includes(orderNumber.value.toLowerCase());
    });
});

const actionRows = computed(() => store.requiredActions.filter((action) => {
    const order = action.order?.order_number ?? '';
    return (status.value === 'all' || action.status === status.value)
        && order.toLowerCase().includes(orderNumber.value.toLowerCase());
}));

async function submitIssue() {
    if (props.mode === 'actions' || !form.description) {
        return;
    }

    await store.createIssue(props.mode === 'tickets' ? 'ticket' : 'claim', {
        order_id: form.order_id ? Number(form.order_id) : null,
        request_type: form.request_type,
        reasons: [form.reason],
        description: form.description,
        contact: {
            name: form.name,
            email: form.email,
            phone: form.phone,
        },
    });

    form.description = '';
}
</script>

<template>
    <div class="grid gap-5">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">{{ title }}</h2>
                <p class="text-slate-600">Track order-linked conversations, claims, and production blockers.</p>
            </div>
        </div>

        <div class="grid gap-5" :class="props.mode === 'actions' ? '' : 'xl:grid-cols-[1fr_420px]'">
            <Card>
                <div class="mb-4 grid gap-3 md:grid-cols-[auto_1fr] md:items-end">
                    <div class="grid gap-1.5">
                        <span class="text-sm font-medium text-slate-700">Status</span>
                        <Tabs v-model="status" :tabs="statusTabs" />
                    </div>
                    <Input v-model="orderNumber" label="Order Number" placeholder="Enter order number" />
                </div>

                <DataTable min-width="900px">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3">Last Activity</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Order Number</th>
                                <th class="px-4 py-3">Description</th>
                                <th class="px-4 py-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody v-if="props.mode !== 'actions'" class="divide-y divide-slate-200">
                            <tr v-for="issue in issueRows" :key="issue.id">
                                <td class="px-4 py-3">{{ dateLabel(issue.created_at) }}</td>
                                <td class="px-4 py-3">{{ dateLabel(issue.last_activity_at) }}</td>
                                <td class="px-4 py-3"><Badge :tone="statusTone(issue.status)">{{ issue.status }}</Badge></td>
                                <td class="px-4 py-3">{{ issue.order?.order_number ?? '-' }}</td>
                                <td class="px-4 py-3">{{ issue.description }}</td>
                                <td class="px-4 py-3">{{ issue.total_notes_count }} ({{ issue.unread_notes_count }} new)</td>
                            </tr>
                        </tbody>
                        <tbody v-else class="divide-y divide-slate-200">
                            <tr v-for="action in actionRows" :key="action.id">
                                <td class="px-4 py-3">{{ dateLabel(action.last_activity_at) }}</td>
                                <td class="px-4 py-3">{{ dateLabel(action.last_activity_at) }}</td>
                                <td class="px-4 py-3"><Badge :tone="statusTone(action.status)">{{ action.status }}</Badge></td>
                                <td class="px-4 py-3">{{ action.order?.order_number ?? '-' }}</td>
                                <td class="px-4 py-3">{{ action.description }}</td>
                                <td class="px-4 py-3">System</td>
                            </tr>
                        </tbody>
                </DataTable>
                <EmptyState
                    v-if="(props.mode === 'actions' ? actionRows.length : issueRows.length) === 0"
                    class="mt-4"
                    title="No records found"
                    description="The selected status and order filters returned no operational records."
                    :icon="MessageSquarePlus"
                />
            </Card>

            <Card v-if="props.mode !== 'actions'">
                <div class="mb-4 flex items-center gap-2">
                    <MessageSquarePlus class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">Open {{ props.mode === 'tickets' ? 'Ticket' : 'Claim' }}</h3>
                </div>
                <div class="grid gap-4">
                    <Select v-model="form.order_id" label="Select order">
                        <option value="">No order selected</option>
                        <option v-for="order in store.orders" :key="order.id" :value="order.id">{{ order.order_number }} · {{ order.customer_name }}</option>
                    </Select>
                    <Select v-model="form.request_type" label="Request type">
                        <option value="Support">Support</option>
                        <option value="Credit">Credit</option>
                    </Select>
                    <Select v-model="form.reason" label="Reason">
                        <option>Damaged In Transit</option>
                        <option>Never Received</option>
                        <option>Ink Spits</option>
                        <option>Streaking Or Banding</option>
                        <option>Other</option>
                    </Select>
                    <Textarea v-model="form.description" label="Description" required placeholder="Enter description" :rows="5" />
                    <div class="grid gap-3 md:grid-cols-2">
                        <Input v-model="form.name" label="Name" />
                        <Input v-model="form.email" label="Email" />
                    </div>
                    <Input v-model="form.phone" label="Phone" />
                    <Button :disabled="!form.description" @click="submitIssue">Submit</Button>
                </div>
            </Card>
        </div>
    </div>
</template>
