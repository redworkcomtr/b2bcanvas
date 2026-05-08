<script setup lang="ts">
import { Bell, Mail } from 'lucide-vue-next';

import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import Input from '@/components/ui/Input.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { usePortalStore } from '@/stores/portal';

const store = usePortalStore();

const labels: Record<string, string> = {
    ORDER_SHIPPED: 'Order shipped',
    ORDER_ACTION_NEEDED: 'Order action needed',
    ORDER_ISSUE_COMMENT_ADDED: 'Order issue comment added',
    ORDER_VALIDATION_FAILED: 'Order validation failed',
};
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
                        class="rounded-lg border border-slate-200 p-4"
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
    </div>
</template>
