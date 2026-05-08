<script setup lang="ts">
import { ArrowLeft, CheckCircle2, Clock3, FileText, Image, LifeBuoy, MapPin, PackageCheck, Pencil, Printer, Save, Truck } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';

import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { dateLabel, money, statusTone } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';
import type { AuditLog, MediaFile, Order, OrderStatusEvent, RequiredAction } from '@/types/portal';

const props = defineProps<{ uuid: string }>();
const store = usePortalStore();
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const statusMessage = ref('');
const detailOrder = ref<Order | null>(null);

const addressForm = reactive({
    customer_name: '',
    shipping_service: '',
    tracking_number: '',
    tracking_url: '',
    line1: '',
    line2: '',
    city: '',
    state: '',
    postal_code: '',
    country: 'US',
});
const notesForm = reactive({ notes: '' });
const transitionForm = reactive({
    status: '',
    note: '',
    tracking_number: '',
    tracking_url: '',
});

const fallbackOrder = computed(() => store.orders.find((item) => item.uuid === props.uuid) ?? null);
const order = computed(() => detailOrder.value ?? fallbackOrder.value);
const address = computed(() => order.value?.shipping_address ?? {});
const statusEvents = computed<OrderStatusEvent[]>(() => order.value?.statusEvents ?? order.value?.status_events ?? []);
const auditLogs = computed<AuditLog[]>(() => order.value?.auditLogs ?? order.value?.audit_logs ?? []);
const requiredActions = computed<RequiredAction[]>(() => order.value?.requiredActions ?? order.value?.required_actions ?? []);
const mediaFiles = computed<MediaFile[]>(() => order.value?.mediaFiles ?? order.value?.media_files ?? []);

const transitionMap: Record<string, string[]> = {
    draft: ['validation_failed', 'action_needed', 'verified', 'cancelled'],
    validation_failed: ['action_needed', 'verified', 'cancelled'],
    action_needed: ['verified', 'cancelled'],
    verified: ['submitted', 'action_needed', 'cancelled'],
    submitted: ['in_production', 'action_needed', 'cancelled'],
    in_production: ['shipped', 'action_needed', 'cancelled'],
    shipped: ['closed'],
    closed: [],
    cancelled: [],
};
const allowedStatuses = computed(() => transitionMap[order.value?.status ?? ''] ?? []);

function hydrateForms(source: Order) {
    addressForm.customer_name = source.customer_name;
    addressForm.shipping_service = source.shipping_service ?? '';
    addressForm.tracking_number = source.tracking_number ?? '';
    addressForm.tracking_url = source.tracking_url ?? '';
    addressForm.line1 = source.shipping_address?.line1 ?? '';
    addressForm.line2 = source.shipping_address?.line2 ?? '';
    addressForm.city = source.shipping_address?.city ?? '';
    addressForm.state = source.shipping_address?.state ?? '';
    addressForm.postal_code = source.shipping_address?.postal_code ?? '';
    addressForm.country = source.shipping_address?.country ?? 'US';
    notesForm.notes = source.notes ?? '';
    transitionForm.status = allowedStatuses.value[0] ?? '';
    transitionForm.note = '';
    transitionForm.tracking_number = source.tracking_number ?? '';
    transitionForm.tracking_url = source.tracking_url ?? '';
}

async function loadOrder() {
    loading.value = true;
    errorMessage.value = '';
    try {
        detailOrder.value = await store.fetchOrder(props.uuid);
        hydrateForms(detailOrder.value);
    } catch {
        errorMessage.value = 'Order details could not be loaded.';
    } finally {
        loading.value = false;
    }
}

async function saveAddress() {
    if (!order.value) {
        return;
    }

    saving.value = true;
    statusMessage.value = '';
    errorMessage.value = '';

    try {
        detailOrder.value = await store.updateOrderAddress(order.value, {
            customer_name: addressForm.customer_name,
            shipping_service: addressForm.shipping_service || null,
            tracking_number: addressForm.tracking_number || null,
            tracking_url: addressForm.tracking_url || null,
            shipping_address: {
                line1: addressForm.line1,
                line2: addressForm.line2,
                city: addressForm.city,
                state: addressForm.state,
                postal_code: addressForm.postal_code,
                country: addressForm.country,
            },
        });
        hydrateForms(detailOrder.value);
        statusMessage.value = 'Shipping and tracking details saved.';
    } catch {
        errorMessage.value = 'Shipping details could not be saved.';
    } finally {
        saving.value = false;
    }
}

async function saveNotes() {
    if (!order.value) {
        return;
    }

    detailOrder.value = await store.updateOrderNotes(order.value, notesForm.notes);
    statusMessage.value = 'Internal notes updated.';
}

async function transitionOrder() {
    if (!order.value || !transitionForm.status) {
        return;
    }

    saving.value = true;
    statusMessage.value = '';
    errorMessage.value = '';

    try {
        detailOrder.value = (await store.transitionOrder(order.value, {
            status: transitionForm.status,
            note: transitionForm.note,
            tracking_number: transitionForm.tracking_number || null,
            tracking_url: transitionForm.tracking_url || null,
        })).order;
        hydrateForms(detailOrder.value);
        statusMessage.value = 'Order lifecycle updated.';
    } catch {
        errorMessage.value = 'Status transition is not allowed for this order.';
    } finally {
        saving.value = false;
    }
}

function displayStatus(status: string | null) {
    return status ? status.replace('_', ' ') : 'created';
}

watch(() => props.uuid, () => {
    void loadOrder();
});

onMounted(() => {
    if (fallbackOrder.value) {
        hydrateForms(fallbackOrder.value);
    }
    void loadOrder();
});
</script>

<template>
    <div v-if="order" class="grid gap-5">
        <div class="flex flex-col justify-between gap-4 xl:flex-row xl:items-center">
            <div class="flex items-center gap-3">
                <RouterLink to="/orders">
                    <Button variant="ghost" size="icon" aria-label="Back to orders">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                </RouterLink>
                <div>
                    <h2 class="text-2xl font-bold text-slate-950">{{ order.order_number }}</h2>
                    <p class="text-slate-600">{{ order.customer_name }} · {{ order.shipping_service ?? 'No service selected' }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Badge :tone="statusTone(order.status)">{{ displayStatus(order.status) }}</Badge>
                <Badge v-if="requiredActions.length" tone="warning">{{ requiredActions.length }} required action</Badge>
            </div>
        </div>

        <Alert v-if="statusMessage" tone="success" title="Order updated" :description="statusMessage" />
        <Alert v-if="errorMessage" tone="danger" title="Order workflow warning" :description="errorMessage" />

        <div class="grid gap-5 xl:grid-cols-[1fr_380px]">
            <div class="grid gap-5">
                <Card>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase text-slate-500">Lifecycle</p>
                            <h3 class="mt-1 text-xl font-bold text-slate-950">{{ displayStatus(order.status) }}</h3>
                        </div>
                        <div class="grid gap-1 text-sm text-slate-600 md:grid-cols-4 md:gap-6">
                            <span><strong>Order:</strong> {{ dateLabel(order.order_date) }}</span>
                            <span><strong>Submitted:</strong> {{ dateLabel(order.submitted_at) }}</span>
                            <span><strong>Shipped:</strong> {{ dateLabel(order.shipped_at) }}</span>
                            <span><strong>Total:</strong> {{ money(order.totals?.subtotal_cents ?? 0) }}</span>
                        </div>
                    </div>
                </Card>

                <Card>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <Truck class="h-5 w-5 text-teal-700" />
                            <h3 class="text-lg font-bold text-slate-950">Shipping, Address, Tracking</h3>
                        </div>
                        <Button :disabled="saving" size="sm" @click="saveAddress"><Save class="h-4 w-4" /> Save</Button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <Input v-model="addressForm.customer_name" label="Customer" required />
                        <Input v-model="addressForm.shipping_service" label="Shipping service" />
                        <Input v-model="addressForm.tracking_number" label="Tracking number" />
                        <Input v-model="addressForm.tracking_url" label="Tracking URL" />
                        <Input v-model="addressForm.line1" label="Address line 1" required />
                        <Input v-model="addressForm.line2" label="Address line 2" />
                        <Input v-model="addressForm.city" label="City" required />
                        <Input v-model="addressForm.state" label="State" />
                        <Input v-model="addressForm.postal_code" label="Postal code" required />
                        <Input v-model="addressForm.country" label="Country" required />
                    </div>

                    <p class="mt-4 text-sm leading-6 text-slate-600">
                        Current: {{ order.customer_name }}, {{ address.line1 }}, {{ address.city }}, {{ address.state }} {{ address.postal_code }}, {{ address.country }}
                    </p>
                </Card>

                <Card v-for="item in order.items" :key="item.id">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase text-slate-500">Item SKU: {{ item.item_sku ?? 'N/A' }}</p>
                            <h3 class="mt-1 text-xl font-bold text-slate-950">{{ item.item_name }}</h3>
                        </div>
                        <Badge tone="info">{{ item.quantity }} item</Badge>
                    </div>

                    <div class="grid gap-4 md:grid-cols-4">
                        <div><p class="text-xs text-slate-500">Product Code</p><p class="font-semibold">{{ item.product_code ?? 'Unmapped' }}</p></div>
                        <div><p class="text-xs text-slate-500">Product Type</p><p class="font-semibold">{{ item.product_type ?? 'Pending' }}</p></div>
                        <div><p class="text-xs text-slate-500">Panels</p><p class="font-semibold">{{ item.panel_summary ?? '-' }}</p></div>
                        <div><p class="text-xs text-slate-500">Price</p><p class="font-semibold">{{ money(item.variant?.price_cents ?? 0) }}</p></div>
                    </div>

                    <div class="mt-5 grid gap-4 lg:grid-cols-[1fr_280px]">
                        <div>
                            <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <Image class="h-4 w-4" />
                                Design Images
                            </div>
                            <img
                                v-if="item.design_images?.[0]"
                                :src="item.design_images[0]"
                                :alt="item.item_name"
                                class="aspect-[16/9] w-full rounded-md border border-slate-200 object-cover"
                            >
                            <EmptyState v-else title="No design image" description="Artwork files will appear here after upload." :icon="Image" />
                        </div>

                        <div>
                            <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <Printer class="h-4 w-4" />
                                Print Options
                            </div>
                            <ul v-if="item.options && Object.keys(item.options).length" class="grid gap-2 text-sm text-slate-700">
                                <li v-for="(value, key) in item.options" :key="key" class="rounded-md bg-slate-50 px-3 py-2">
                                    <strong>{{ key }}:</strong> {{ value }}
                                </li>
                            </ul>
                            <EmptyState v-else title="No options" description="No item-level production options are attached." :icon="Printer" />
                        </div>
                    </div>
                </Card>

                <Card>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <FileText class="h-5 w-5 text-teal-700" />
                            <h3 class="text-lg font-bold text-slate-950">Internal Notes</h3>
                        </div>
                        <Button size="sm" variant="outline" @click="saveNotes"><Pencil class="h-4 w-4" /> Save notes</Button>
                    </div>
                    <Textarea v-model="notesForm.notes" rows="5" placeholder="Production notes, customer context, or internal handling instructions..." />
                </Card>
            </div>

            <div class="grid gap-5">
                <Card>
                    <div class="mb-4 flex items-center gap-2">
                        <PackageCheck class="h-5 w-5 text-teal-700" />
                        <h3 class="text-lg font-bold text-slate-950">Status Transition</h3>
                    </div>
                    <div v-if="allowedStatuses.length" class="grid gap-3">
                        <Select v-model="transitionForm.status" label="Next status">
                            <option v-for="status in allowedStatuses" :key="status" :value="status">{{ displayStatus(status) }}</option>
                        </Select>
                        <Textarea v-model="transitionForm.note" label="Transition note" rows="3" placeholder="Reason, production handoff, or tracking context..." />
                        <Input v-if="transitionForm.status === 'shipped'" v-model="transitionForm.tracking_number" label="Tracking number" />
                        <Input v-if="transitionForm.status === 'shipped'" v-model="transitionForm.tracking_url" label="Tracking URL" />
                        <Button :disabled="saving" @click="transitionOrder"><CheckCircle2 class="h-4 w-4" /> Move status</Button>
                    </div>
                    <Alert v-else tone="info" title="No forward transitions" description="This order is in a terminal lifecycle state." />
                </Card>

                <Card>
                    <div class="mb-4 flex items-center gap-2">
                        <Clock3 class="h-5 w-5 text-teal-700" />
                        <h3 class="text-lg font-bold text-slate-950">Timeline</h3>
                    </div>
                    <div v-if="statusEvents.length" class="grid gap-3">
                        <div v-for="event in statusEvents" :key="event.id" class="rounded-md border border-slate-200 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-semibold text-slate-950">{{ displayStatus(event.from_status) }} -> {{ displayStatus(event.to_status) }}</p>
                                <Badge tone="neutral">{{ dateLabel(event.created_at) }}</Badge>
                            </div>
                            <p v-if="event.note" class="mt-2 text-sm text-slate-600">{{ event.note }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ event.user?.name ?? 'System' }}</p>
                        </div>
                    </div>
                    <EmptyState v-else title="No status events" description="Lifecycle events will appear after the first transition." :icon="Clock3" />
                </Card>

                <Card>
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-950">Issues</h3>
                        <RouterLink to="/issues/tickets">
                            <Button size="sm"><LifeBuoy class="h-4 w-4" /> Open Ticket</Button>
                        </RouterLink>
                    </div>
                    <div v-if="order.issues?.length" class="grid gap-3">
                        <div v-for="issue in order.issues" :key="issue.id" class="rounded-md border border-slate-200 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge :tone="statusTone(issue.status)">{{ issue.status }}</Badge>
                                <Badge :tone="issue.priority === 'urgent' ? 'danger' : issue.priority === 'high' ? 'warning' : 'neutral'">{{ issue.priority }}</Badge>
                                <Badge v-if="issue.unread_notes_count" tone="info">{{ issue.unread_notes_count }} unread</Badge>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ issue.type === 'ticket' ? 'Ticket' : 'Claim' }} #{{ issue.id }}</p>
                            <p class="mt-1 text-sm text-slate-700">{{ issue.description }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ issue.comments?.length ?? 0 }} comments · {{ issue.assigned_to?.name ?? issue.assignedTo?.name ?? 'Unassigned' }}</p>
                        </div>
                    </div>
                    <EmptyState v-else title="No issues for this order" description="Tickets and claims linked to this order will appear here." :icon="LifeBuoy" />
                </Card>

                <Card>
                    <div class="mb-4 flex items-center gap-2">
                        <MapPin class="h-5 w-5 text-teal-700" />
                        <h3 class="text-lg font-bold text-slate-950">Required Actions</h3>
                    </div>
                    <div v-if="requiredActions.length" class="grid gap-3">
                        <div v-for="action in requiredActions" :key="action.id" class="rounded-md border border-amber-200 bg-amber-50 p-3">
                            <Badge tone="warning">{{ action.status }}</Badge>
                            <p class="mt-2 font-semibold text-amber-950">{{ action.title }}</p>
                            <p class="text-sm text-amber-900">{{ action.description }}</p>
                        </div>
                    </div>
                    <EmptyState v-else title="No open actions" description="This order has no blocking customer or production action." :icon="MapPin" />
                </Card>

                <Card>
                    <div class="mb-4 flex items-center gap-2">
                        <FileText class="h-5 w-5 text-teal-700" />
                        <h3 class="text-lg font-bold text-slate-950">Files & Audit</h3>
                    </div>
                    <div v-if="mediaFiles.length" class="mb-4 grid gap-2">
                        <a v-for="file in mediaFiles" :key="file.id" :href="file.url" class="rounded-md border border-slate-200 p-3 text-sm font-semibold text-teal-800">
                            {{ file.original_name }}
                        </a>
                    </div>
                    <EmptyState v-else class="mb-4" title="No order files" description="Artwork, proofs, and production files will appear here when attached." :icon="FileText" />

                    <div v-if="auditLogs.length" class="grid gap-2">
                        <div v-for="log in auditLogs" :key="log.id" class="rounded-md bg-slate-50 p-3 text-sm">
                            <p class="font-semibold text-slate-900">{{ log.event }}</p>
                            <p class="text-slate-500">{{ dateLabel(log.created_at) }} · {{ log.user?.name ?? 'System' }}</p>
                        </div>
                    </div>
                    <EmptyState v-else title="No audit events" description="Address, notes, and lifecycle changes are logged here." :icon="FileText" />
                </Card>
            </div>
        </div>
    </div>

    <EmptyState v-else-if="!loading" title="Order not found" description="Return to the orders list and select another order." />
</template>
