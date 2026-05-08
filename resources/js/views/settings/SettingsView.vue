<script setup lang="ts">
import { Bell, Mail, ShieldCheck, UserPlus, Users } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DataTable from '@/components/ui/Table.vue';
import { ApiError, usePortalStore } from '@/stores/portal';
import type { User } from '@/types/portal';

const store = usePortalStore();
const inviteLoading = ref(false);
const inviteMessage = ref('');
const inviteError = ref('');
const roleOptions: User['role'][] = ['admin', 'operations', 'support', 'viewer'];
const canManageUsers = computed(() => store.can('manage_users'));
const inviteForm = reactive({
    name: '',
    email: '',
    role: 'viewer' as User['role'],
});

const labels: Record<string, string> = {
    ORDER_SHIPPED: 'Order shipped',
    ORDER_ACTION_NEEDED: 'Order action needed',
    ORDER_ISSUE_COMMENT_ADDED: 'Order issue comment added',
    ORDER_VALIDATION_FAILED: 'Order validation failed',
};

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
</script>

<template>
    <div class="grid gap-5">
        <div>
            <h2 class="text-2xl font-bold text-slate-950">Settings</h2>
            <p class="text-slate-600">Manage tenant identity and event-based e-mail notifications.</p>
        </div>

        <div class="grid gap-5 xl:grid-cols-[360px_1fr]">
            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <Mail class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">Account</h3>
                </div>
                <div class="grid gap-3 text-sm">
                    <div class="rounded-md bg-slate-50 p-3"><strong>Tenant:</strong> {{ store.tenant?.name }}</div>
                    <div class="rounded-md bg-slate-50 p-3"><strong>User:</strong> {{ store.user?.name }}</div>
                    <div class="rounded-md bg-slate-50 p-3"><strong>Role:</strong> {{ store.user?.role }}</div>
                    <div class="rounded-md bg-slate-50 p-3"><strong>Permissions:</strong> {{ store.abilities.length }}</div>
                </div>
            </Card>

            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <Bell class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">Email Notifications</h3>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div
                        v-for="subscription in store.notificationSubscriptions"
                        :key="subscription.id"
                        class="min-w-0 rounded-lg border border-slate-200 p-4"
                    >
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <h4 class="font-bold text-slate-950">{{ labels[subscription.event] ?? subscription.event }}</h4>
                                <p class="text-sm text-slate-500">{{ subscription.event }}</p>
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
                        <Users class="h-5 w-5 text-teal-700" />
                        <div>
                            <h3 class="text-lg font-bold text-slate-950">Workspace Users</h3>
                            <p class="text-sm text-slate-500">Tenant-scoped team, role, and active/passive controls.</p>
                        </div>
                    </div>
                    <Badge :tone="canManageUsers ? 'success' : 'neutral'">{{ canManageUsers ? 'Manage users' : 'Read only' }}</Badge>
                </div>

                <DataTable min-width="920px">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last Login</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="user in store.users" :key="user.id" class="bg-white">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-950">{{ user.name }}</p>
                                <p class="text-slate-500">{{ user.email }}</p>
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
                            <td class="px-4 py-3 text-slate-600">{{ user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : '-' }}</td>
                            <td class="px-4 py-3">
                                <Button
                                    v-if="canManageUsers && user.role !== 'owner'"
                                    size="sm"
                                    :variant="user.active ? 'outline' : 'default'"
                                    @click="toggleActive(user)"
                                >
                                    {{ user.active ? 'Deactivate' : 'Activate' }}
                                </Button>
                                <span v-else class="text-sm text-slate-400">Locked</span>
                            </td>
                        </tr>
                    </tbody>
                </DataTable>
            </Card>

            <Card>
                <div class="mb-4 flex items-center gap-2">
                    <UserPlus class="h-5 w-5 text-teal-700" />
                    <h3 class="text-lg font-bold text-slate-950">Invite User</h3>
                </div>

                <form v-if="canManageUsers" class="grid gap-4" @submit.prevent="inviteUser">
                    <Input v-model="inviteForm.name" label="Name" required placeholder="Aylin Operator" />
                    <Input v-model="inviteForm.email" label="Email" type="email" required placeholder="user@company.com" />
                    <Select v-model="inviteForm.role" label="Role">
                        <option v-for="role in roleOptions" :key="role" :value="role">{{ role }}</option>
                    </Select>
                    <p v-if="inviteMessage" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">{{ inviteMessage }}</p>
                    <p v-if="inviteError" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{{ inviteError }}</p>
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

                <div v-if="store.userInvites.length" class="mt-5 border-t border-slate-200 pt-4">
                    <p class="mb-3 text-sm font-bold text-slate-950">Pending invites</p>
                    <div class="grid gap-2">
                        <div v-for="invite in store.userInvites" :key="invite.id" class="rounded-md bg-slate-50 p-3 text-sm">
                            <p class="font-semibold text-slate-950">{{ invite.email }}</p>
                            <p class="text-slate-500">{{ invite.role }} · {{ invite.status }}</p>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </div>
</template>
