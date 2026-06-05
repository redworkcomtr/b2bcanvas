<script setup lang="ts">
import { Bell, Building2, Eye, Mail, RefreshCcw, Save, ShieldCheck, UserPlus, Users } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Dialog from '@/components/ui/Dialog.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { ApiError, usePortalStore } from '@/stores/portal';
import type { NotificationLog, User } from '@/types/portal';
import { dateLabel } from '@/lib/utils';

type NotificationLogPreview = {
    subject: string;
    body_html: string;
    body_text: string | null;
    status: string;
    attempts: number;
    max_attempts: number;
    error_message: string | null;
};

const store = usePortalStore();
const inviteLoading = ref(false);
const inviteMessage = ref('');
const inviteError = ref('');
const logsLoading = ref(false);
const logsLoadError = ref('');
const logsDialogOpen = ref(false);
const selectedLog = ref<NotificationLog | null>(null);
const selectedLogRetryEmail = ref('');
const logRetryMessage = ref('');
const logRetryBusy = ref(false);
const previewLog = ref<NotificationLogPreview | null>(null);
const tenantSaving = ref(false);
const tenantMessage = ref('');
const tenantError = ref('');
const roleOptions = computed<User['role'][]>(() => (store.user?.role === 'owner'
    ? ['owner', 'admin', 'operations', 'support', 'viewer']
    : ['admin', 'operations', 'support', 'viewer']));
const canManageUsers = computed(() => store.can('manage_users'));
const canManageTenant = computed(() => store.can('manage_tenant'));
const tenantForm = reactive({
    name: '',
    support_email: '',
    default_shipping_service: '',
    currency: '',
    timezone: '',
    order_prefix: '',
    settings_json: '{}',
});
const inviteForm = reactive({
    name: '',
    email: '',
    role: 'viewer' as User['role'],
});

const labels = computed<Record<string, string>>(() => store.notificationEvents);

const logsSummary = computed(() => ({
    total: store.notificationLogs.length,
    sent: store.notificationLogs.filter((log) => log.status === 'sent').length,
    queued: store.notificationLogs.filter((log) => log.status === 'queued').length,
    failed: store.notificationLogs.filter((log) => log.status === 'failed').length,
}));

function syncTenantForm() {
    const settings = store.tenant?.settings ?? {};
    tenantForm.name = store.tenant?.name ?? '';
    tenantForm.support_email = store.tenant?.support_email ?? '';
    tenantForm.default_shipping_service = typeof settings.default_shipping_service === 'string' ? settings.default_shipping_service : '';
    tenantForm.currency = typeof settings.currency === 'string' ? settings.currency : '';
    tenantForm.timezone = typeof settings.timezone === 'string' ? settings.timezone : '';
    tenantForm.order_prefix = typeof settings.order_prefix === 'string' ? settings.order_prefix : '';
    tenantForm.settings_json = JSON.stringify(settings, null, 2);
}

function toneForNotificationStatus(status: string): 'neutral' | 'success' | 'warning' | 'danger' | 'info' {
    if (status === 'sent') {
        return 'success';
    }

    if (status === 'failed') {
        return 'danger';
    }

    if (status === 'queued' || status === 'pending') {
        return 'info';
    }

    return 'neutral';
}

async function loadNotificationLogs() {
    logsLoadError.value = '';
    logsLoading.value = true;

    try {
        await store.loadNotificationLogs();
    } catch {
        logsLoadError.value = 'Notification logs could not be loaded.';
    } finally {
        logsLoading.value = false;
    }
}

async function openNotificationLog(log: NotificationLog) {
    selectedLog.value = log;
    selectedLogRetryEmail.value = log.recipient_email;
    logRetryMessage.value = '';
    previewLog.value = null;
    logsDialogOpen.value = true;

    try {
        previewLog.value = await store.previewNotificationLog(log);
    } catch {
        logRetryMessage.value = 'Preview could not be loaded.';
    }
}

function closeNotificationLogDialog(open: boolean) {
    if (!open) {
        logsDialogOpen.value = false;
        selectedLog.value = null;
        previewLog.value = null;
        logRetryMessage.value = '';
        selectedLogRetryEmail.value = '';
    }
}

async function retrySelectedNotificationLog() {
    if (!selectedLog.value) {
        return;
    }

    logRetryBusy.value = true;
    logRetryMessage.value = '';

    try {
        const payload = selectedLogRetryEmail.value && selectedLogRetryEmail.value !== selectedLog.value.recipient_email
            ? { recipient_email: selectedLogRetryEmail.value }
            : {};

        const updated = await store.retryNotificationLog(selectedLog.value, payload);
        selectedLog.value = updated;
        selectedLogRetryEmail.value = updated.recipient_email;
        previewLog.value = await store.previewNotificationLog(updated);
        await loadNotificationLogs();
        logRetryMessage.value = 'Notification was queued again. The selected recipient has been updated.';
    } catch {
        logRetryMessage.value = 'Retry request could not be sent. Check the address and try again.';
    } finally {
        logRetryBusy.value = false;
    }
}

async function saveTenantSettings() {
    tenantSaving.value = true;
    tenantMessage.value = '';
    tenantError.value = '';

    try {
        const parsedSettings = JSON.parse(tenantForm.settings_json || '{}') as Record<string, unknown>;

        await store.updateTenant({
            name: tenantForm.name,
            support_email: tenantForm.support_email || null,
            settings: {
                ...parsedSettings,
                default_shipping_service: tenantForm.default_shipping_service,
                currency: tenantForm.currency,
                timezone: tenantForm.timezone,
                order_prefix: tenantForm.order_prefix,
            },
        });

        syncTenantForm();
        tenantMessage.value = 'Tenant settings saved.';
    } catch (exception) {
        tenantError.value = exception instanceof SyntaxError
            ? 'Settings JSON is invalid.'
            : exception instanceof ApiError
                ? exception.message
                : 'Tenant settings could not be saved.';
    } finally {
        tenantSaving.value = false;
    }
}

function roleTone(role: string): 'neutral' | 'success' | 'warning' | 'danger' | 'info' {
    return role === 'owner' || role === 'admin' ? 'info' : role === 'viewer' ? 'neutral' : 'success';
}

async function inviteUser() {
    inviteLoading.value = true;
    inviteMessage.value = '';
    inviteError.value = '';

    try {
        await store.inviteUser(inviteForm);
        inviteMessage.value = 'Invitation created. Activate the user when onboarding is complete.';
        inviteForm.name = '';
        inviteForm.email = '';
        inviteForm.role = 'viewer';
    } catch (exception) {
        inviteError.value = exception instanceof ApiError ? exception.message : 'Invitation could not be created.';
    } finally {
        inviteLoading.value = false;
    }
}

async function updateRole(user: User, role: User['role']) {
    await store.updateUser(user, { role });
}

async function toggleActive(user: User) {
    await store.updateUser(user, { active: !user.active });
}

watch(
    () => store.tenant,
    () => syncTenantForm(),
    { immediate: true, deep: true },
);

watch(
    () => roleOptions.value,
    (options) => {
        if (! options.includes(inviteForm.role)) {
            inviteForm.role = 'viewer';
        }
    },
    { immediate: true },
);

watch(
    () => canManageUsers.value,
    (canManage) => {
        if (canManage) {
            void loadNotificationLogs();
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="app-page">
        <div class="page-heading">
            <h2>Settings</h2>
            <p>Manage tenant identity and event-based e-mail notifications.</p>
        </div>

        <div class="grid gap-5 xl:grid-cols-[360px_1fr]">
            <div class="grid gap-5">
                <Card>
                    <div class="mb-4 flex items-center gap-2">
                        <Mail class="h-5 w-5 text-[#18181b]" />
                        <h3 class="panel-title">Account</h3>
                    </div>
                    <div class="grid gap-3 text-sm">
                        <div class="rounded-2xl bg-white p-3"><strong>Tenant:</strong> {{ store.tenant?.name }}</div>
                        <div class="rounded-2xl bg-white p-3"><strong>User:</strong> {{ store.user?.name }}</div>
                        <div class="rounded-2xl bg-white p-3"><strong>Role:</strong> {{ store.user?.role }}</div>
                        <div class="rounded-2xl bg-white p-3"><strong>Permissions:</strong> {{ store.abilities.length }}</div>
                    </div>
                </Card>

                <Card>
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <Building2 class="h-5 w-5 text-[#18181b]" />
                            <div>
                                <h3 class="panel-title">Tenant Settings</h3>
                                <p class="panel-caption">Identity, support routing, and defaults.</p>
                            </div>
                        </div>
                        <Badge :tone="canManageTenant ? 'success' : 'neutral'">{{ canManageTenant ? 'Owner' : 'Read only' }}</Badge>
                    </div>

                    <div class="grid gap-4">
                        <Input v-model="tenantForm.name" label="Workspace name" :disabled="!canManageTenant" />
                        <Input v-model="tenantForm.support_email" label="Support email" type="email" :disabled="!canManageTenant" />
                        <Input v-model="tenantForm.default_shipping_service" label="Default shipping service" :disabled="!canManageTenant" />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <Input v-model="tenantForm.currency" label="Currency" maxlength="3" :disabled="!canManageTenant" />
                            <Input v-model="tenantForm.timezone" label="Timezone" :disabled="!canManageTenant" />
                        </div>
                        <Input v-model="tenantForm.order_prefix" label="Order prefix" :disabled="!canManageTenant" />
                        <Textarea v-model="tenantForm.settings_json" label="Settings JSON" :rows="5" :disabled="!canManageTenant" />
                        <p v-if="tenantMessage" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">{{ tenantMessage }}</p>
                        <p v-if="tenantError" class="rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ tenantError }}</p>
                        <Button v-if="canManageTenant" :disabled="tenantSaving || !tenantForm.name" @click="saveTenantSettings">
                            <Save class="h-4 w-4" />
                            {{ tenantSaving ? 'Saving...' : 'Save tenant settings' }}
                        </Button>
                    </div>
                </Card>
            </div>

            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <Bell class="h-5 w-5 text-[#18181b]" />
                    <h3 class="panel-title">Email Notifications</h3>
                </div>

                <div class="grid gap-4 2xl:grid-cols-2">
                    <div
                        v-for="subscription in store.notificationSubscriptions"
                        :key="subscription.id"
                        class="min-w-0 rounded-2xl bg-white p-4"
                    >
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h4 class="font-semibold text-[#18181b]">{{ labels[subscription.event] ?? subscription.event }}</h4>
                                <p class="text-sm text-[#71717a]">{{ subscription.event }}</p>
                            </div>
                            <Badge :tone="subscription.is_subscribed ? 'success' : 'neutral'">
                                {{ subscription.is_subscribed ? 'Subscribed' : 'Paused' }}
                            </Badge>
                        </div>
                        <Input v-model="subscription.email" label="Email" />
                        <div class="mt-3 flex gap-2">
                            <Button
                                :variant="subscription.is_subscribed ? 'destructive' : 'default'"
                                size="sm"
                                @click="subscription.is_subscribed = !subscription.is_subscribed; store.updateSubscription(subscription)"
                            >
                                {{ subscription.is_subscribed ? 'Unsubscribe' : 'Subscribe' }}
                            </Button>
                            <Button variant="outline" size="sm" @click="store.updateSubscription(subscription)">Save</Button>
                        </div>
                    </div>
                    <EmptyState
                        v-if="store.notificationSubscriptions.length === 0"
                        title="No notification subscriptions"
                        description="Notification events will appear after the tenant notification seed or setup flow runs."
                        :icon="Bell"
                    />
                </div>
            </Card>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_380px]">
            <Card>
                <div class="mb-4 flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <div class="flex items-center gap-2">
                        <Users class="h-5 w-5 text-[#18181b]" />
                        <div>
                            <h3 class="panel-title">Workspace Users</h3>
                            <p class="panel-caption">Tenant-scoped team, role, and active/passive controls.</p>
                        </div>
                    </div>
                    <Badge :tone="canManageUsers ? 'success' : 'neutral'">{{ canManageUsers ? 'Manage users' : 'Read only' }}</Badge>
                </div>

                <DataTable min-width="920px">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last Login</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <tr v-for="user in store.users" :key="user.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[#18181b]">{{ user.name }}</p>
                                <p class="text-[#71717a]">{{ user.email }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <Select
                                    v-if="canManageUsers && user.role !== 'owner'"
                                    :model-value="user.role"
                                    @update:model-value="updateRole(user, $event as User['role'])"
                                >
                                    <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
                                </Select>
                                <Badge v-else :tone="roleTone(user.role)">{{ user.role }}</Badge>
                            </td>
                            <td class="px-4 py-3"><Badge :tone="user.active ? 'success' : 'warning'">{{ user.active ? 'Active' : 'Invited' }}</Badge></td>
                            <td class="px-4 py-3 text-[#4c4546]">{{ user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : '-' }}</td>
                            <td class="px-4 py-3">
                                <Button
                                    v-if="canManageUsers && user.role !== 'owner'"
                                    size="sm"
                                    :variant="user.active ? 'outline' : 'default'"
                                    @click="toggleActive(user)"
                                >
                                    {{ user.active ? 'Deactivate' : 'Activate' }}
                                </Button>
                                <span v-else class="text-sm text-[#a1a1aa]">Locked</span>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </Card>

            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <UserPlus class="h-5 w-5 text-[#18181b]" />
                    <h3 class="panel-title">Invite User</h3>
                </div>

                <form v-if="canManageUsers" class="grid gap-4" @submit.prevent="inviteUser">
                    <Input v-model="inviteForm.name" label="Name" required placeholder="Aylin Operator" />
                    <Input v-model="inviteForm.email" label="Email" type="email" required placeholder="user@company.com" />
                    <Select v-model="inviteForm.role" label="Role">
                        <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
                    </Select>
                    <p v-if="inviteMessage" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">{{ inviteMessage }}</p>
                    <p v-if="inviteError" class="rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ inviteError }}</p>
                    <Button type="submit" :disabled="inviteLoading || !inviteForm.name || !inviteForm.email">
                        {{ inviteLoading ? 'Creating invite...' : 'Create invite' }}
                    </Button>
                </form>

                <EmptyState
                    v-else
                    title="User management is restricted"
                    description="Only owner and admin roles can invite users or change account access."
                    :icon="ShieldCheck"
                />

                <div v-if="store.userInvites.length" class="mt-5 border-t border-zinc-200/70 pt-4">
                    <p class="mb-3 text-sm font-semibold text-[#18181b]">Pending invites</p>
                    <div class="grid gap-2">
                        <div v-for="invite in store.userInvites" :key="invite.id" class="rounded-2xl bg-white p-3 text-sm">
                            <p class="font-semibold text-[#18181b]">{{ invite.email }}</p>
                            <p class="text-[#71717a]">{{ invite.role }} · {{ invite.status }}</p>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <Card>
            <div class="mb-4 flex flex-col justify-between gap-3 md:flex-row md:items-start">
                <div class="flex items-center gap-2">
                    <Bell class="h-5 w-5 text-[#18181b]" />
                    <div>
                        <h3 class="panel-title">Notification Logs</h3>
                        <p class="panel-caption">Email delivery history with preview and retry actions.</p>
                    </div>
                </div>
                <Button
                    v-if="canManageUsers"
                    size="sm"
                    variant="outline"
                    :disabled="logsLoading"
                    @click="loadNotificationLogs"
                >
                    {{ logsLoading ? 'Refreshing...' : 'Refresh logs' }}
                </Button>
            </div>

            <template v-if="canManageUsers">
                <div class="mb-4 grid gap-3 md:grid-cols-4">
                    <div class="rounded-2xl bg-white p-3">
                        <p class="text-xs uppercase text-[#71717a]">Total</p>
                        <p class="mt-1 text-xl font-bold text-[#18181b]">{{ logsSummary.total }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-3">
                        <p class="text-xs uppercase text-[#71717a]">Sent</p>
                        <p class="mt-1 text-xl font-bold text-emerald-700">{{ logsSummary.sent }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-3">
                        <p class="text-xs uppercase text-[#71717a]">Queued</p>
                        <p class="mt-1 text-xl font-bold text-sky-700">{{ logsSummary.queued }}</p>
                    </div>
                    <div class="rounded-2xl bg-white p-3">
                        <p class="text-xs uppercase text-[#71717a]">Failed</p>
                        <p class="mt-1 text-xl font-bold text-red-700">{{ logsSummary.failed }}</p>
                    </div>
                </div>

                <p v-if="logsLoadError" class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ logsLoadError }}</p>

                <DataTable min-width="980px">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3">Recipient</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Attempts</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <tr v-if="logsLoading">
                            <td colspan="6" class="px-4 py-4 text-[#71717a]">Loading notification logs...</td>
                        </tr>
                        <tr v-else-if="store.notificationLogs.length === 0">
                            <td colspan="6" class="px-4 py-4">
                                <EmptyState
                                    title="No email logs yet"
                                    description="Notification events will appear when the system dispatches portal emails."
                                    :icon="Mail"
                                />
                            </td>
                        </tr>
                        <tr v-for="log in store.notificationLogs" :key="log.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-[#18181b]">{{ labels[log.event] ?? log.event }}</p>
                                <p class="text-xs text-[#71717a]">#{{ log.id }}</p>
                            </td>
                            <td class="px-4 py-3 text-[#4c4546]">{{ log.recipient_email }}</td>
                            <td class="px-4 py-3"><Badge :tone="toneForNotificationStatus(log.status)">{{ log.status }}</Badge></td>
                            <td class="px-4 py-3 text-[#4c4546]">{{ log.attempts }}/{{ log.max_attempts }}</td>
                            <td class="px-4 py-3 text-[#4c4546]">{{ dateLabel(log.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Button size="sm" variant="outline" @click="openNotificationLog(log)">
                                    <Eye class="h-4 w-4" />
                                    Preview
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </template>

            <EmptyState
                v-else
                title="Log access is restricted"
                description="Manage-users permission is required to inspect notification logs, preview email bodies and retry delivery."
                :icon="ShieldCheck"
            />
        </Card>
    </div>

    <Dialog :open="logsDialogOpen" title="Notification log" description="Preview the email content and retry delivery if needed." @update:open="closeNotificationLogDialog">
        <template v-if="selectedLog">
            <div class="grid gap-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase text-[#71717a]">Event</p>
                        <p class="font-semibold text-[#18181b]">{{ selectedLog.event }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-[#71717a]">Status</p>
                        <Badge :tone="toneForNotificationStatus(selectedLog.status)">{{ selectedLog.status }}</Badge>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-[#71717a]">Created</p>
                        <p class="font-semibold text-[#18181b]">{{ dateLabel(selectedLog.created_at) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-[#71717a]">Attempts</p>
                        <p class="font-semibold text-[#18181b]">{{ selectedLog.attempts }}/{{ selectedLog.max_attempts }}</p>
                    </div>
                </div>

                <Input v-model="selectedLogRetryEmail" label="Retry recipient" type="email" />

                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-xs uppercase text-[#71717a]">Subject</p>
                    <p class="font-semibold text-[#18181b]">{{ previewLog?.subject ?? selectedLog.subject }}</p>
                    <p v-if="selectedLog.message_id" class="mt-2 text-xs text-[#71717a]">Message-ID: {{ selectedLog.message_id }}</p>
                    <p v-if="previewLog?.error_message || selectedLog.error_message" class="mt-2 text-xs font-medium text-red-700">{{ previewLog?.error_message ?? selectedLog.error_message }}</p>
                </div>

                <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                    <p class="text-xs uppercase text-[#71717a]">Body preview</p>
                    <p v-if="previewLog?.body_text !== null" class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap text-sm text-[#4c4546]">
                        {{ previewLog?.body_text || 'No text content.' }}
                    </p>
                    <div v-else class="prose mt-2 max-h-64 overflow-auto" v-html="previewLog?.body_html ?? selectedLog.body_html" />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button size="sm" :disabled="logRetryBusy" @click="retrySelectedNotificationLog">
                        <RefreshCcw class="h-4 w-4" />
                        {{ logRetryBusy ? 'Retrying...' : 'Retry send' }}
                    </Button>
                    <Button size="sm" variant="outline" @click="logsDialogOpen = false">Close</Button>
                </div>

                <p v-if="logRetryMessage" class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                    {{ logRetryMessage }}
                </p>
            </div>
        </template>
    </Dialog>
</template>
