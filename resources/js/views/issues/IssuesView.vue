<script setup lang="ts">
import { AlertTriangle, CheckCircle2, MessageSquarePlus, RefreshCcw, ShieldAlert } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import Tabs from '@/components/ui/Tabs.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { dateLabel, humanize, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';
import type { RequiredAction } from '@/types/portal';

const props = defineProps<{ mode: 'tickets' | 'claims' | 'actions' }>();
const store = usePortalStore();
const status = ref('all');
const orderNumber = ref('');
const selectedActionId = ref<number | null>(null);
const actionComment = ref('');
const resolutionNote = ref('');
const resolutionVariantId = ref('');
const actionError = ref('');
const actionBusy = ref(false);
const activeActionStatuses = ['open', 'in_progress', 'escalated'];

const addressResolution = reactive({
    customer_name: '',
    line1: '',
    line2: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
});

const form = reactive({
    order_id: '',
    request_type: props.mode === 'claims' ? 'Credit' : 'Support',
    reason: 'Never Received',
    description: '',
    name: store.user?.name ?? '',
    email: store.user?.email ?? '',
    phone: '',
});

const issueStatusTabs = [
    { label: 'All', value: 'all' },
    { label: 'Open', value: 'open' },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Closed', value: 'closed' },
];

const actionStatusTabs = [
    { label: 'All', value: 'all' },
    { label: 'Open', value: 'open' },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Escalated', value: 'escalated' },
    { label: 'Resolved', value: 'resolved' },
];

const statusTabs = computed(() => (props.mode === 'actions' ? actionStatusTabs : issueStatusTabs));

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

const selectedAction = computed<RequiredAction | null>(() => {
    if (!actionRows.value.length) {
        return null;
    }

    return actionRows.value.find((action) => action.id === selectedActionId.value) ?? actionRows.value[0] ?? null;
});

const selectedPayload = computed(() => Object.entries(selectedAction.value?.payload ?? {})
    .filter(([, value]) => ['string', 'number', 'boolean'].includes(typeof value))
    .slice(0, 10));

const selectedComments = computed(() => selectedAction.value?.comments ?? []);
const selectedActionActive = computed(() => Boolean(selectedAction.value && activeActionStatuses.includes(selectedAction.value.status)));
const canResolveSelected = computed(() => {
    if (!selectedAction.value || !selectedActionActive.value) {
        return false;
    }

    if (selectedAction.value.type === 'product_mapping_required') {
        return Boolean(resolutionVariantId.value);
    }

    if (selectedAction.value.type === 'address_error') {
        return Boolean(addressResolution.line1 && addressResolution.city && addressResolution.country);
    }

    return true;
});

watch(() => props.mode, () => {
    status.value = 'all';
    selectedActionId.value = null;
});

watch(() => selectedAction.value?.id, () => {
    actionError.value = '';
    resolutionNote.value = '';
    actionComment.value = '';
    resolutionVariantId.value = String(selectedAction.value?.resolution_payload?.product_variant_id ?? selectedAction.value?.payload?.resolved_product_variant_id ?? '');
    hydrateAddress(selectedAction.value);
}, { immediate: true });

function hydrateAddress(action: RequiredAction | null) {
    const source = (action?.order?.shipping_address ?? action?.payload?.shipping_address ?? {}) as Record<string, string>;

    addressResolution.customer_name = action?.order?.customer_name ?? String(action?.payload?.customer_name ?? '');
    addressResolution.line1 = source.line1 ?? source.address1 ?? '';
    addressResolution.line2 = source.line2 ?? source.address2 ?? '';
    addressResolution.city = source.city ?? '';
    addressResolution.state = source.state ?? '';
    addressResolution.postal_code = source.postal_code ?? source.zip ?? '';
    addressResolution.country = source.country ?? '';
}

function selectAction(action: RequiredAction) {
    selectedActionId.value = action.id;
}

function actionTypeLabel(type: string) {
    return ({
        product_mapping_required: 'Missing mapping',
        missing_mapping: 'Missing mapping',
        invalid_artwork: 'Invalid artwork',
        address_error: 'Address error',
        duplicate_order: 'Duplicate order',
        product_unavailable: 'Product unavailable',
    } as Record<string, string>)[type] ?? humanize(type);
}

function actionTypeCaption(action: RequiredAction) {
    return ({
        product_mapping_required: 'Link marketplace item data to a production SKU.',
        missing_mapping: 'Link marketplace item data to a production SKU.',
        invalid_artwork: 'Review artwork requirements and capture the corrected asset.',
        address_error: 'Correct the recipient and shipping address before release.',
        duplicate_order: 'Decide whether the duplicate row should be skipped or processed.',
        product_unavailable: 'Choose an alternate SKU or document the customer decision.',
    } as Record<string, string>)[action.type] ?? 'Resolve the operational blocker and revalidate the record.';
}

function payloadText(value: unknown) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return String(value);
}

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

async function runActionOperation(operation: () => Promise<unknown>) {
    actionError.value = '';
    actionBusy.value = true;
    try {
        await operation();
    } catch (error) {
        actionError.value = error instanceof Error ? error.message : 'The action could not be completed.';
    } finally {
        actionBusy.value = false;
    }
}

async function addActionComment() {
    const action = selectedAction.value;
    const body = actionComment.value.trim();
    if (!action || !body) {
        return;
    }

    await runActionOperation(async () => {
        const updated = await store.addRequiredActionComment(action, { body });
        selectedActionId.value = updated.id;
        actionComment.value = '';
    });
}

async function resolveAction() {
    const action = selectedAction.value;
    if (!action || !canResolveSelected.value) {
        return;
    }

    const resolution: Record<string, unknown> = { note: resolutionNote.value.trim() };

    if (action.type === 'product_mapping_required') {
        resolution.product_variant_id = Number(resolutionVariantId.value);
    }

    if (action.type === 'address_error') {
        resolution.customer_name = addressResolution.customer_name;
        resolution.shipping_address = {
            line1: addressResolution.line1,
            line2: addressResolution.line2,
            city: addressResolution.city,
            state: addressResolution.state,
            postal_code: addressResolution.postal_code,
            country: addressResolution.country,
        };
    }

    await runActionOperation(async () => {
        const updated = await store.resolveRequiredAction(action, {
            resolution,
            comment: resolutionNote.value.trim() || null,
        });
        selectedActionId.value = updated.id;
        resolutionNote.value = '';
    });
}

async function escalateAction() {
    const action = selectedAction.value;
    if (!action) {
        return;
    }

    await runActionOperation(async () => {
        const updated = await store.escalateRequiredAction(action, {
            priority: 'urgent',
            comment: resolutionNote.value.trim() || null,
        });
        selectedActionId.value = updated.id;
        resolutionNote.value = '';
    });
}

async function reopenAction() {
    const action = selectedAction.value;
    if (!action) {
        return;
    }

    await runActionOperation(async () => {
        const updated = await store.reopenRequiredAction(action, {
            comment: resolutionNote.value.trim() || null,
        });
        selectedActionId.value = updated.id;
        resolutionNote.value = '';
    });
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

        <div v-if="props.mode === 'actions'" class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_430px]">
            <Card>
                <div class="mb-4 grid gap-3 md:grid-cols-[auto_1fr] md:items-end">
                    <div class="grid gap-1.5">
                        <span class="text-sm font-medium text-slate-700">Status</span>
                        <Tabs v-model="status" :tabs="statusTabs" />
                    </div>
                    <Input v-model="orderNumber" label="Order Number" placeholder="Enter order number" />
                </div>

                <DataTable min-width="980px">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <tr
                            v-for="action in actionRows"
                            :key="action.id"
                            class="cursor-pointer hover:bg-slate-50"
                            :class="selectedAction?.id === action.id ? 'bg-teal-50/70' : ''"
                            tabindex="0"
                            @click="selectAction(action)"
                            @keydown.enter="selectAction(action)"
                        >
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-950">{{ actionTypeLabel(action.type) }}</div>
                                <div class="text-xs text-slate-500">#{{ action.id }}</div>
                            </td>
                            <td class="px-4 py-3"><Badge :tone="statusTone(action.status)">{{ humanize(action.status) }}</Badge></td>
                            <td class="px-4 py-3"><Badge :tone="action.priority === 'urgent' ? 'danger' : action.priority === 'high' ? 'warning' : 'neutral'">{{ action.priority }}</Badge></td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ action.order?.order_number ?? 'Import queue' }}</td>
                            <td class="max-w-[360px] px-4 py-3 text-slate-600">{{ action.description }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ dateLabel(action.last_activity_at) }}</td>
                        </tr>
                    </tbody>
                </DataTable>
                <EmptyState
                    v-if="actionRows.length === 0"
                    class="mt-4"
                    title="No required actions"
                    description="The selected filters do not contain production blockers."
                    :icon="MessageSquarePlus"
                />
            </Card>

            <Card>
                <div v-if="selectedAction" class="grid gap-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <Badge :tone="statusTone(selectedAction.status)">{{ humanize(selectedAction.status) }}</Badge>
                                <Badge :tone="selectedAction.priority === 'urgent' ? 'danger' : selectedAction.priority === 'high' ? 'warning' : 'neutral'">{{ selectedAction.priority }}</Badge>
                            </div>
                            <h3 class="text-xl font-bold text-slate-950">{{ selectedAction.title }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ actionTypeCaption(selectedAction) }}</p>
                        </div>
                        <AlertTriangle class="h-6 w-6 shrink-0 text-amber-600" />
                    </div>

                    <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm font-semibold text-slate-900">{{ selectedAction.description }}</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div v-for="[key, value] in selectedPayload" :key="key" class="min-w-0 rounded-md border border-slate-200 bg-white px-3 py-2">
                                <div class="text-[11px] font-semibold uppercase text-slate-500">{{ humanize(key) }}</div>
                                <div class="truncate text-sm font-medium text-slate-800">{{ payloadText(value) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4">
                        <div v-if="selectedAction.type === 'product_mapping_required'" class="grid gap-3">
                            <Select v-model="resolutionVariantId" label="Production SKU" required>
                                <option value="">Select production variant</option>
                                <option v-for="variant in store.variants" :key="variant.id" :value="variant.id">
                                    {{ variant.sku }} · {{ variant.name }}
                                </option>
                            </Select>
                            <Textarea v-model="resolutionNote" label="Resolution note" placeholder="Mapping decision, customer context, or production note" :rows="3" />
                        </div>

                        <div v-else-if="selectedAction.type === 'address_error'" class="grid gap-3">
                            <Input v-model="addressResolution.customer_name" label="Customer name" />
                            <Input v-model="addressResolution.line1" label="Address line 1" required />
                            <Input v-model="addressResolution.line2" label="Address line 2" />
                            <div class="grid gap-3 sm:grid-cols-2">
                                <Input v-model="addressResolution.city" label="City" required />
                                <Input v-model="addressResolution.state" label="State" />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <Input v-model="addressResolution.postal_code" label="Postal code" />
                                <Input v-model="addressResolution.country" label="Country" required />
                            </div>
                            <Textarea v-model="resolutionNote" label="Resolution note" placeholder="Address verification details" :rows="3" />
                        </div>

                        <div v-else class="grid gap-3">
                            <Textarea v-model="resolutionNote" label="Resolution note" placeholder="Document the decision before resolving or escalating" :rows="5" />
                        </div>

                        <div v-if="actionError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
                            {{ actionError }}
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button v-if="selectedActionActive" :disabled="actionBusy || !canResolveSelected" @click="resolveAction">
                                <CheckCircle2 class="h-4 w-4" />
                                Resolve
                            </Button>
                            <Button v-if="selectedActionActive" variant="outline" :disabled="actionBusy" @click="escalateAction">
                                <ShieldAlert class="h-4 w-4" />
                                Escalate
                            </Button>
                            <Button v-if="selectedAction.status === 'resolved'" variant="outline" :disabled="actionBusy" @click="reopenAction">
                                <RefreshCcw class="h-4 w-4" />
                                Reopen
                            </Button>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h4 class="font-semibold text-slate-950">Activity</h4>
                            <span class="text-xs font-medium text-slate-500">{{ selectedComments.length }} comments</span>
                        </div>
                        <div class="grid max-h-72 gap-3 overflow-auto pr-1">
                            <div v-for="comment in selectedComments" :key="comment.id" class="rounded-md border border-slate-200 bg-white p-3">
                                <div class="mb-1 flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-900">{{ comment.user?.name ?? 'System' }}</span>
                                    <span class="text-xs text-slate-500">{{ dateLabel(comment.created_at) }}</span>
                                </div>
                                <p class="whitespace-pre-line text-sm text-slate-600">{{ comment.body }}</p>
                            </div>
                            <p v-if="selectedComments.length === 0" class="rounded-md border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                                No comments yet.
                            </p>
                        </div>
                        <div class="mt-4 grid gap-3">
                            <Textarea v-model="actionComment" label="Add comment" placeholder="Leave a note for operations" :rows="3" />
                            <Button variant="outline" :disabled="!actionComment.trim() || actionBusy" @click="addActionComment">
                                <MessageSquarePlus class="h-4 w-4" />
                                Add Comment
                            </Button>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-else
                    title="No action selected"
                    description="Select a production blocker from the queue."
                    :icon="AlertTriangle"
                />
            </Card>
        </div>

        <div v-else class="grid gap-5 xl:grid-cols-[1fr_420px]">
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
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="issue in issueRows" :key="issue.id">
                            <td class="px-4 py-3">{{ dateLabel(issue.created_at) }}</td>
                            <td class="px-4 py-3">{{ dateLabel(issue.last_activity_at) }}</td>
                            <td class="px-4 py-3"><Badge :tone="statusTone(issue.status)">{{ humanize(issue.status) }}</Badge></td>
                            <td class="px-4 py-3">{{ issue.order?.order_number ?? '-' }}</td>
                            <td class="px-4 py-3">{{ issue.description }}</td>
                            <td class="px-4 py-3">{{ issue.total_notes_count }} ({{ issue.unread_notes_count }} new)</td>
                        </tr>
                    </tbody>
                </DataTable>
                <EmptyState
                    v-if="issueRows.length === 0"
                    class="mt-4"
                    title="No records found"
                    description="The selected status and order filters returned no operational records."
                    :icon="MessageSquarePlus"
                />
            </Card>

            <Card>
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
