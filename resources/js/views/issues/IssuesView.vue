<script setup lang="ts">
import { AlertTriangle, Banknote, CheckCircle2, Eye, MessageSquarePlus, Paperclip, RefreshCcw, Save, ShieldAlert } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import FileDropzone from '@/components/ui/FileDropzone.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import Tabs from '@/components/ui/Tabs.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { dateLabel, humanize, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';
import type { Issue, MediaFile, RequiredAction } from '@/types/portal';

const props = defineProps<{ mode: 'tickets' | 'claims' | 'actions' }>();
const store = usePortalStore();
const status = ref(props.mode === 'actions' ? 'open' : 'all');
const orderNumber = ref('');
const selectedIssueId = ref<number | null>(null);
const issueComment = ref('');
const issueCommentInternal = ref(false);
const issueAttachments = ref<MediaFile[]>([]);
const issueError = ref('');
const issueBusy = ref(false);
const selectedActionId = ref<number | null>(null);
const actionComment = ref('');
const resolutionNote = ref('');
const resolutionVariantId = ref('');
const correctedArtworkFiles = ref<MediaFile[]>([]);
const duplicateDecision = ref<'skip' | 'process_with_new_number' | 'cancel_existing'>('skip');
const duplicateReplacementOrderNumber = ref('');
const actionError = ref('');
const actionBusy = ref(false);
const claimDecision = ref<'credit' | 'refund' | 'reprint' | 'reject'>('credit');
const claimAmount = ref('');
const claimCurrency = ref('USD');
const claimFinanceReference = ref('');
const claimProductionOutcome = ref('');
const claimDecisionNotes = ref('');
const claimEvidence = ref<MediaFile[]>([]);
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

const issueUpdate = reactive({
    status: '',
    priority: 'normal',
    assigned_to_id: '',
});

const claimRequestTypes = ['Credit', 'Refund', 'Reprint', 'Reject'];
const claimDecisions = ['credit', 'refund', 'reprint', 'reject'];

const issueStatusTabs = [
    { label: 'All', value: 'all' },
    { label: 'Open', value: 'open' },
    { label: 'In Progress', value: 'in_progress' },
    { label: 'Waiting', value: 'waiting_customer' },
    { label: 'Resolved', value: 'resolved' },
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

const selectedIssue = computed<Issue | null>(() => {
    if (!issueRows.value.length) {
        return null;
    }

    return issueRows.value.find((issue) => issue.id === selectedIssueId.value) ?? issueRows.value[0] ?? null;
});

const selectedIssueComments = computed(() => selectedIssue.value?.comments ?? []);
const selectedIssueHasResolution = computed(() => Boolean(selectedIssue.value?.claim_resolution));
const supportUsers = computed(() => store.users.filter((user) => user.active && ['owner', 'admin', 'support'].includes(user.role)));

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

    if (selectedAction.value.type === 'invalid_artwork') {
        return correctedArtworkFiles.value.length > 0;
    }

    if (selectedAction.value.type === 'duplicate_order') {
        return duplicateDecision.value !== 'process_with_new_number' || Boolean(duplicateReplacementOrderNumber.value.trim());
    }

    if (selectedAction.value.type === 'product_unavailable') {
        return Boolean(resolutionVariantId.value);
    }

    return true;
});

const selectedIssueCanResolve = computed(() => Boolean(
    selectedIssue.value
    && props.mode === 'claims'
    && !['resolved', 'closed'].includes(selectedIssue.value.status),
));

const claimAmountRequired = computed(() => ['credit', 'refund'].includes(claimDecision.value));
const claimAmountNumeric = computed(() => Number(claimAmount.value));

watch(() => props.mode, () => {
    status.value = props.mode === 'actions' ? 'open' : 'all';
    selectedIssueId.value = null;
    selectedActionId.value = null;
    form.request_type = props.mode === 'claims' ? 'Credit' : 'Support';
    form.reason = props.mode === 'claims' ? 'Damaged In Transit' : 'Never Received';
});

watch(() => selectedIssue.value?.id, () => {
    issueError.value = '';
    issueComment.value = '';
    issueCommentInternal.value = false;
    issueAttachments.value = [];
    issueUpdate.status = selectedIssue.value?.status ?? '';
    issueUpdate.priority = selectedIssue.value?.priority ?? 'normal';
    issueUpdate.assigned_to_id = String(selectedIssue.value?.assigned_to_id ?? '');
    claimDecision.value = selectedIssueClaimDecision(selectedIssue.value);
    claimAmount.value = selectedIssue.value?.claim_resolution?.amount_cents?.toString() ?? '';
    claimCurrency.value = selectedIssue.value?.claim_resolution?.currency ?? 'USD';
    claimFinanceReference.value = selectedIssue.value?.claim_resolution?.finance_reference ?? '';
    claimProductionOutcome.value = selectedIssue.value?.claim_resolution?.production_outcome ?? '';
    claimDecisionNotes.value = '';
    claimEvidence.value = [];
}, { immediate: true });

watch(() => selectedAction.value?.id, () => {
    actionError.value = '';
    resolutionNote.value = '';
    actionComment.value = '';
    resolutionVariantId.value = String(selectedAction.value?.resolution_payload?.product_variant_id ?? selectedAction.value?.payload?.resolved_product_variant_id ?? '');
    correctedArtworkFiles.value = [];
    duplicateDecision.value = 'skip';
    duplicateReplacementOrderNumber.value = '';
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

function normalizeClaimDecision(value: string | null | undefined): 'credit' | 'refund' | 'reprint' | 'reject' {
    const decision = (value ?? '').toLowerCase();
    if (claimDecisions.includes(decision)) {
        return decision as 'credit' | 'refund' | 'reprint' | 'reject';
    }

    return 'credit';
}

function selectedIssueClaimDecision(issue: Issue | null) {
    return issue?.claim_resolution
        ? (normalizeClaimDecision(issue.claim_resolution.decision))
        : normalizeClaimDecision(issue?.request_type);
}

function payloadText(value: unknown) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return String(value);
}

function selectIssue(issue: Issue) {
    selectedIssueId.value = issue.id;
}

function priorityTone(priority?: string) {
    if (priority === 'urgent') {
        return 'danger';
    }

    if (priority === 'high') {
        return 'warning';
    }

    if (priority === 'low') {
        return 'info';
    }

    return 'neutral';
}

function assigneeName(issue: Issue) {
    return issue.assigned_to?.name ?? issue.assignedTo?.name ?? 'Unassigned';
}

function attachmentValue(attachment: unknown, key: string, fallback = '') {
    const item = attachment as Record<string, unknown>;

    return String(item[key] ?? fallback);
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

async function runIssueOperation(operation: () => Promise<unknown>) {
    issueError.value = '';
    issueBusy.value = true;
    try {
        await operation();
    } catch (error) {
        issueError.value = error instanceof Error ? error.message : 'The ticket could not be updated.';
    } finally {
        issueBusy.value = false;
    }
}

async function saveSelectedIssue() {
    const issue = selectedIssue.value;
    if (!issue) {
        return;
    }

    await runIssueOperation(async () => {
        const updated = await store.updateIssue(issue, {
            status: issueUpdate.status,
            priority: issueUpdate.priority,
            assigned_to_id: issueUpdate.assigned_to_id ? Number(issueUpdate.assigned_to_id) : null,
        });
        selectedIssueId.value = updated.id;
    });
}

async function markSelectedIssueRead() {
    const issue = selectedIssue.value;
    if (!issue) {
        return;
    }

    await runIssueOperation(async () => {
        const updated = await store.markIssueRead(issue);
        selectedIssueId.value = updated.id;
    });
}

async function uploadIssueAttachment(file: File) {
    await runIssueOperation(async () => {
        const media = await store.uploadFile(file, 'issue_attachment');
        issueAttachments.value = [...issueAttachments.value, media];
    });
}

function removeIssueAttachment(file: MediaFile) {
    issueAttachments.value = issueAttachments.value.filter((item) => item.id !== file.id);
}

async function addIssueComment() {
    const issue = selectedIssue.value;
    const body = issueComment.value.trim();
    if (!issue || !body) {
        return;
    }

    await runIssueOperation(async () => {
        const updated = await store.addIssueComment(issue, {
            body,
            attachments: issueAttachments.value,
            internal: issueCommentInternal.value,
        });
        selectedIssueId.value = updated.id;
        issueComment.value = '';
        issueAttachments.value = [];
        issueCommentInternal.value = false;
    });
}

async function uploadClaimEvidence(file: File) {
    await runIssueOperation(async () => {
        const media = await store.uploadFile(file, 'claim_evidence');
        claimEvidence.value = [...claimEvidence.value, media];
    });
}

function removeClaimEvidence(file: MediaFile) {
    claimEvidence.value = claimEvidence.value.filter((item) => item.id !== file.id);
}

function isValidClaimAmount() {
    return !claimAmountRequired.value || Number.isFinite(claimAmountNumeric.value) && claimAmountNumeric.value >= 0;
}

async function resolveClaim() {
    const issue = selectedIssue.value;
    if (!issue || !selectedIssueCanResolve.value) {
        return;
    }

    if (claimAmountRequired.value && !claimAmount.value.trim()) {
        issueError.value = 'An amount is required for credit and refund decisions.';
        return;
    }

    if (!isValidClaimAmount()) {
        issueError.value = 'The amount must be a valid positive integer (in cents).';
        return;
    }

    await runIssueOperation(async () => {
        const updated = await store.resolveClaim(issue, {
            decision: claimDecision.value,
            amount_cents: claimAmountRequired.value ? claimAmountNumeric.value : undefined,
            currency: claimCurrency.value,
            finance_reference: claimFinanceReference.value.trim() || null,
            production_outcome: claimProductionOutcome.value.trim() || null,
            notes: claimDecisionNotes.value.trim() || null,
            evidence_files: claimEvidence.value,
        });
        selectedIssueId.value = updated.id;
        claimDecisionNotes.value = '';
        claimEvidence.value = [];
    });
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

async function uploadActionArtwork(file: File) {
    await runActionOperation(async () => {
        const media = await store.uploadFile(file, 'artwork');
        correctedArtworkFiles.value = [...correctedArtworkFiles.value, media];
    });
}

function removeActionArtwork(file: MediaFile) {
    correctedArtworkFiles.value = correctedArtworkFiles.value.filter((item) => item.id !== file.id);
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

    if (action.type === 'invalid_artwork') {
        resolution.artwork_media_file_id = correctedArtworkFiles.value[0]?.id;
    }

    if (action.type === 'duplicate_order') {
        resolution.decision = duplicateDecision.value;
        if (duplicateDecision.value === 'process_with_new_number') {
            resolution.replacement_order_number = duplicateReplacementOrderNumber.value.trim();
        }
    }

    if (action.type === 'product_unavailable') {
        resolution.product_variant_id = Number(resolutionVariantId.value);
    }

    await runActionOperation(async () => {
        const updated = await store.resolveRequiredAction(action, {
            resolution,
            comment: resolutionNote.value.trim() || null,
        });
        selectedActionId.value = updated.id;
        resolutionNote.value = '';
        correctedArtworkFiles.value = [];
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
    <div class="app-page">
        <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
            <div class="page-heading">
                <h2>{{ title }}</h2>
                <p>{{ props.mode === 'actions' ? actionRows.length : issueRows.length }} records · {{ status === 'all' ? 'All statuses' : humanize(status) }}</p>
            </div>
        </div>

        <div v-if="props.mode === 'actions'" class="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_430px]">
            <Card>
                <div class="mb-4 grid gap-3 md:grid-cols-[auto_1fr] md:items-end">
                    <div class="grid gap-1.5">
                        <span class="text-sm font-medium text-[#4c4546]">Status</span>
                        <Tabs v-model="status" :tabs="statusTabs" />
                    </div>
                    <Input v-model="orderNumber" label="Order Number" placeholder="Enter order number" />
                </div>

                <DataTable min-width="980px">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <tr
                            v-for="action in actionRows"
                            :key="action.id"
                            class="cursor-pointer"
                            :class="selectedAction?.id === action.id ? 'bg-zinc-200/60 shadow-[inset_3px_0_0_#18181b]' : ''"
                            tabindex="0"
                            @click="selectAction(action)"
                            @keydown.enter="selectAction(action)"
                        >
                            <td class="px-4 py-3">
                                <div class="font-semibold text-[#18181b]">{{ actionTypeLabel(action.type) }}</div>
                                <div class="text-xs text-[#71717a]">#{{ action.id }}</div>
                            </td>
                            <td class="px-4 py-3"><Badge :tone="statusTone(action.status)">{{ humanize(action.status) }}</Badge></td>
                            <td class="px-4 py-3"><Badge :tone="action.priority === 'urgent' ? 'danger' : action.priority === 'high' ? 'warning' : 'neutral'">{{ action.priority }}</Badge></td>
                            <td class="px-4 py-3 font-medium text-[#4c4546]">{{ action.order?.order_number ?? 'Import queue' }}</td>
                            <td class="max-w-[360px] px-4 py-3 text-[#4c4546]">{{ action.description }}</td>
                            <td class="px-4 py-3 text-[#4c4546]">{{ dateLabel(action.last_activity_at) }}</td>
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
                            <h3 class="text-xl font-semibold text-[#18181b]">{{ selectedAction.title }}</h3>
                            <p class="mt-1 text-sm text-[#4c4546]">{{ actionTypeCaption(selectedAction) }}</p>
                        </div>
                        <AlertTriangle class="h-6 w-6 shrink-0 text-amber-600" />
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4">
                        <p class="text-sm font-semibold text-[#18181b]">{{ selectedAction.description }}</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div v-for="[key, value] in selectedPayload" :key="key" class="min-w-0 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2">
                                <div class="text-[11px] font-semibold uppercase text-[#71717a]">{{ humanize(key) }}</div>
                                <div class="truncate text-sm font-medium text-[#18181b]">{{ payloadText(value) }}</div>
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

                        <div v-else-if="selectedAction.type === 'invalid_artwork'" class="grid gap-3">
                            <FileDropzone label="Corrected artwork" description="Upload the replacement design or print-ready file." accept="image/*,.pdf" @selected="uploadActionArtwork" />
                            <div v-if="correctedArtworkFiles.length" class="grid gap-2">
                                <div v-for="file in correctedArtworkFiles" :key="file.id" class="flex items-center justify-between gap-3 rounded-2xl bg-white px-3 py-2">
                                    <span class="min-w-0 truncate text-sm font-medium text-[#4c4546]">
                                        <Paperclip class="mr-1 inline h-4 w-4 text-[#71717a]" />
                                        {{ file.original_name }}
                                    </span>
                                    <Button size="sm" variant="ghost" @click="removeActionArtwork(file)">Remove</Button>
                                </div>
                            </div>
                            <Textarea v-model="resolutionNote" label="Resolution note" placeholder="Document what changed in the corrected artwork" :rows="3" />
                        </div>

                        <div v-else-if="selectedAction.type === 'duplicate_order'" class="grid gap-3">
                            <Select v-model="duplicateDecision" label="Duplicate decision" required>
                                <option value="skip">Skip this import row</option>
                                <option value="process_with_new_number">Process with a new order number</option>
                                <option value="cancel_existing">Cancel existing order and skip row</option>
                            </Select>
                            <Input
                                v-if="duplicateDecision === 'process_with_new_number'"
                                v-model="duplicateReplacementOrderNumber"
                                label="Replacement order number"
                                required
                                placeholder="WEB-9002-R1"
                            />
                            <Textarea v-model="resolutionNote" label="Resolution note" placeholder="Document the duplicate review decision" :rows="3" />
                        </div>

                        <div v-else-if="selectedAction.type === 'product_unavailable'" class="grid gap-3">
                            <Select v-model="resolutionVariantId" label="Alternate production SKU" required>
                                <option value="">Select alternate variant</option>
                                <option v-for="variant in store.variants" :key="variant.id" :value="variant.id">
                                    {{ variant.sku }} · {{ variant.name }}
                                </option>
                            </Select>
                            <Textarea v-model="resolutionNote" label="Resolution note" placeholder="Document substitution approval or production note" :rows="3" />
                        </div>

                        <div v-else class="grid gap-3">
                            <Textarea v-model="resolutionNote" label="Resolution note" placeholder="Document the decision before resolving or escalating" :rows="5" />
                        </div>

                        <div v-if="actionError" class="rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
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

                    <div class="border-t border-zinc-200/70 pt-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h4 class="font-semibold text-[#18181b]">Activity</h4>
                            <span class="text-xs font-medium text-[#71717a]">{{ selectedComments.length }} comments</span>
                        </div>
                        <div class="grid max-h-72 gap-3 overflow-auto pr-1">
                            <div v-for="comment in selectedComments" :key="comment.id" class="rounded-2xl bg-white p-3">
                                <div class="mb-1 flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-[#18181b]">{{ comment.user?.name ?? 'System' }}</span>
                                    <span class="text-xs text-[#71717a]">{{ dateLabel(comment.created_at) }}</span>
                                </div>
                                <p class="whitespace-pre-line text-sm text-[#4c4546]">{{ comment.body }}</p>
                            </div>
                            <p v-if="selectedComments.length === 0" class="rounded-2xl bg-white p-4 text-sm text-[#71717a]">
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

        <div v-else class="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_430px]">
            <Card>
                <div class="mb-4 grid gap-3 md:grid-cols-[auto_1fr] md:items-end">
                    <div class="grid gap-1.5">
                        <span class="text-sm font-medium text-[#4c4546]">Status</span>
                        <Tabs v-model="status" :tabs="statusTabs" />
                    </div>
                    <Input v-model="orderNumber" label="Order Number" placeholder="Enter order number" />
                </div>

                <DataTable min-width="900px">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Priority</th>
                            <th class="px-4 py-3">Assignee</th>
                            <th class="px-4 py-3">Order Number</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <tr
                            v-for="issue in issueRows"
                            :key="issue.id"
                            class="cursor-pointer"
                            :class="selectedIssue?.id === issue.id ? 'bg-zinc-200/60 shadow-[inset_3px_0_0_#18181b]' : ''"
                            tabindex="0"
                            @click="selectIssue(issue)"
                            @keydown.enter="selectIssue(issue)"
                        >
                            <td class="px-4 py-3">{{ dateLabel(issue.created_at) }}</td>
                            <td class="px-4 py-3"><Badge :tone="statusTone(issue.status)">{{ humanize(issue.status) }}</Badge></td>
                            <td class="px-4 py-3"><Badge :tone="priorityTone(issue.priority)">{{ issue.priority }}</Badge></td>
                            <td class="px-4 py-3 text-[#4c4546]">{{ assigneeName(issue) }}</td>
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

            <div class="grid gap-5">
                <Card>
                    <div v-if="selectedIssue" class="grid gap-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <Badge :tone="statusTone(selectedIssue.status)">{{ humanize(selectedIssue.status) }}</Badge>
                                    <Badge :tone="priorityTone(selectedIssue.priority)">{{ selectedIssue.priority }}</Badge>
                                    <Badge v-if="selectedIssue.unread_notes_count" tone="info">{{ selectedIssue.unread_notes_count }} unread</Badge>
                                </div>
                                <h3 class="text-xl font-semibold text-[#18181b]">{{ props.mode === 'tickets' ? 'Ticket' : 'Claim' }} #{{ selectedIssue.id }}</h3>
                                <p class="mt-1 text-sm text-[#4c4546]">{{ selectedIssue.description }}</p>
                            </div>
                            <MessageSquarePlus class="h-6 w-6 shrink-0 text-[#18181b]" />
                        </div>

                        <div class="grid gap-3 rounded-2xl bg-white p-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <Select v-model="issueUpdate.status" label="Status">
                                    <option value="open">Open</option>
                                    <option value="in_progress">In progress</option>
                                    <option value="waiting_customer">Waiting customer</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </Select>
                                <Select v-model="issueUpdate.priority" label="Priority">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </Select>
                            </div>
                            <Select v-model="issueUpdate.assigned_to_id" label="Assignee">
                                <option value="">Unassigned</option>
                                <option v-for="user in supportUsers" :key="user.id" :value="user.id">{{ user.name }} · {{ user.role }}</option>
                            </Select>
                            <div v-if="selectedIssue.order" class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm">
                                <span class="font-semibold text-[#4c4546]">Linked order</span>
                                <RouterLink :to="`/orders/${selectedIssue.order.uuid}`" class="ml-2 font-bold text-[#18181b]">{{ selectedIssue.order.order_number }}</RouterLink>
                            </div>
                        <div v-if="issueError" class="rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
                            {{ issueError }}
                        </div>
                        <div v-if="props.mode === 'claims' && selectedIssueHasResolution" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm">
                            <p class="mb-2 text-xs font-semibold uppercase text-emerald-800">Latest claim decision</p>
                            <div class="grid gap-2">
                                <p class="font-semibold text-emerald-900">
                                    <template v-if="selectedIssue.claim_resolution?.decision && selectedIssue.claim_resolution?.amount_cents">
                                        {{ selectedIssue.claim_resolution.decision }} · {{ selectedIssue.claim_resolution.amount_cents }} {{ selectedIssue.claim_resolution.currency }}
                                    </template>
                                    <template v-else>
                                        {{ selectedIssue.claim_resolution?.decision ?? 'Recorded' }}
                                    </template>
                                </p>
                                <p v-if="selectedIssue.claim_resolution?.notes" class="text-[#4c4546]">{{ selectedIssue.claim_resolution.notes }}</p>
                                <p v-if="selectedIssue.claim_resolution?.finance_reference" class="text-[#4c4546]">Finance ref: {{ selectedIssue.claim_resolution.finance_reference }}</p>
                                <p v-if="selectedIssue.claim_resolution?.production_outcome" class="text-[#4c4546]">{{ selectedIssue.claim_resolution.production_outcome }}</p>
                            </div>
                        </div>
                        <div v-if="props.mode === 'claims'" class="grid gap-3 rounded-2xl bg-white p-4">
                            <div class="mb-1 flex items-center justify-between">
                                <h4 class="font-semibold text-[#18181b]">Claim Decision</h4>
                                <Badge v-if="selectedIssue.status === 'resolved' || selectedIssue.status === 'closed'" tone="success">
                                    {{ selectedIssue.status }}
                                </Badge>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <Select v-model="claimDecision" label="Decision">
                                    <option value="credit">Credit</option>
                                    <option value="refund">Refund</option>
                                    <option value="reprint">Reprint</option>
                                    <option value="reject">Reject</option>
                                </Select>
                                <Input v-if="claimAmountRequired" v-model="claimAmount" label="Amount (cents)" type="number" min="0" step="1" />
                                <Input v-if="!claimAmountRequired" v-model="claimAmount" label="Amount (optional)" type="number" min="0" step="1" />
                                <Input v-model="claimCurrency" label="Currency" />
                                <Input v-model="claimFinanceReference" label="Finance reference" />
                                <Input v-model="claimProductionOutcome" label="Production outcome" />
                            </div>
                            <Textarea v-model="claimDecisionNotes" label="Decision notes" placeholder="Explain why this claim decision was taken." :rows="3" />
                            <FileDropzone label="Attach evidence" description="Attach proof files that support the claim decision." accept="image/*,.pdf,.txt,.csv" @selected="uploadClaimEvidence" />
                            <div v-if="claimEvidence.length" class="grid gap-2">
                                <div v-for="file in claimEvidence" :key="file.id" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm">
                                    <span class="truncate font-medium text-[#4c4546]">{{ file.original_name }}</span>
                                    <Button variant="ghost" size="sm" @click="removeClaimEvidence(file)">Remove</Button>
                                </div>
                            </div>
                            <Button
                                :disabled="issueBusy || !selectedIssueCanResolve || (claimAmountRequired && !claimAmount)"
                                @click="resolveClaim"
                            >
                                <Banknote class="h-4 w-4" />
                                Resolve Claim
                            </Button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button :disabled="issueBusy" @click="saveSelectedIssue">
                                <Save class="h-4 w-4" />
                                Save
                            </Button>
                            <Button variant="outline" :disabled="issueBusy || selectedIssue.unread_notes_count === 0" @click="markSelectedIssueRead">
                                <Eye class="h-4 w-4" />
                                Mark Read
                            </Button>
                        </div>
                    </div>

                        <div class="border-t border-zinc-200/70 pt-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h4 class="font-semibold text-[#18181b]">Conversation</h4>
                                <span class="text-xs font-medium text-[#71717a]">{{ selectedIssueComments.length }} comments</span>
                            </div>
                            <div class="grid max-h-80 gap-3 overflow-auto pr-1">
                                <div
                                    v-for="comment in selectedIssueComments"
                                    :key="comment.id"
                                    class="rounded-2xl p-3"
                                    :class="comment.internal ? 'bg-amber-50' : 'bg-white'"
                                >
                                    <div class="mb-1 flex items-center justify-between gap-3">
                                        <span class="text-sm font-semibold text-[#18181b]">{{ comment.user?.name ?? 'System' }}</span>
                                        <span class="text-xs text-[#71717a]">{{ dateLabel(comment.created_at) }}</span>
                                    </div>
                                    <Badge v-if="comment.internal" tone="warning">internal</Badge>
                                    <p class="mt-2 whitespace-pre-line text-sm text-[#4c4546]">{{ comment.body }}</p>
                                    <div v-if="comment.attachments?.length" class="mt-3 grid gap-2">
                                        <a
                                            v-for="attachment in comment.attachments"
                                            :key="attachmentValue(attachment, 'id', attachmentValue(attachment, 'url'))"
                                            :href="attachmentValue(attachment, 'url', '#')"
                                            class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs font-semibold text-zinc-950"
                                        >
                                            <Paperclip class="h-3.5 w-3.5" />
                                            {{ attachmentValue(attachment, 'original_name', 'Attachment') }}
                                        </a>
                                    </div>
                                </div>
                                <p v-if="selectedIssueComments.length === 0" class="rounded-2xl bg-white p-4 text-sm text-[#71717a]">
                                    No comments yet.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 border-t border-zinc-200/70 pt-4">
                            <Textarea v-model="issueComment" label="Reply" placeholder="Write a customer-facing reply or internal note" :rows="4" />
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-[#4c4546]">
                                <input v-model="issueCommentInternal" type="checkbox" class="h-4 w-4 rounded border-zinc-300 accent-black">
                                Internal note
                            </label>
                            <FileDropzone label="Attach file" description="Images, PDFs, or support documents are stored before the reply is sent." accept="image/*,.pdf,.txt" @selected="uploadIssueAttachment" />
                            <div v-if="issueAttachments.length" class="grid gap-2">
                                <div v-for="file in issueAttachments" :key="file.id" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm">
                                    <span class="truncate font-medium text-[#4c4546]">{{ file.original_name }}</span>
                                    <Button variant="ghost" size="sm" @click="removeIssueAttachment(file)">Remove</Button>
                                </div>
                            </div>
                            <Button :disabled="!issueComment.trim() || issueBusy" @click="addIssueComment">
                                <MessageSquarePlus class="h-4 w-4" />
                                Add Reply
                            </Button>
                        </div>
                    </div>

                    <EmptyState
                        v-else
                        title="No issue selected"
                        description="Select a ticket or claim from the list."
                        :icon="MessageSquarePlus"
                    />
                </Card>

                <Card>
                    <div class="mb-4 flex items-center gap-2">
                        <MessageSquarePlus class="h-5 w-5 text-[#18181b]" />
                        <h3 class="panel-title">Open {{ props.mode === 'tickets' ? 'ticket' : 'claim' }}</h3>
                    </div>
                    <div class="grid gap-4">
                        <Select v-model="form.order_id" label="Select order">
                            <option value="">No order selected</option>
                            <option v-for="order in store.orders" :key="order.id" :value="order.id">{{ order.order_number }} · {{ order.customer_name }}</option>
                        </Select>
                        <Select v-model="form.request_type" label="Request type">
                            <template v-if="props.mode === 'claims'">
                                <option v-for="type in claimRequestTypes" :key="type" :value="type">{{ type }}</option>
                            </template>
                            <template v-else>
                                <option>Support</option>
                            </template>
                        </Select>
                        <Select v-model="form.reason" label="Reason">
                            <template v-if="props.mode === 'claims'">
                                <option>Damaged</option>
                                <option>Lost</option>
                                <option>Wrong Item</option>
                                <option>Late</option>
                                <option>Other</option>
                            </template>
                            <template v-else>
                                <option>Damaged In Transit</option>
                                <option>Never Received</option>
                                <option>Ink Spits</option>
                                <option>Streaking Or Banding</option>
                                <option>Other</option>
                            </template>
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
    </div>
</template>
