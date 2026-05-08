import { defineStore } from 'pinia';

import type {
    ImportPreview,
    Issue,
    NotificationSubscription,
    Order,
    PortalPayload,
    ProductMapping,
    ProductType,
    RequiredAction,
    Tenant,
    User,
} from '@/types/portal';

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers ?? {}),
        },
        ...options,
    });

    if (!response.ok) {
        throw new Error(`Request failed with ${response.status}`);
    }

    return response.json() as Promise<T>;
}

export const usePortalStore = defineStore('portal', {
    state: () => ({
        loaded: false,
        loading: false,
        tenant: null as Tenant | null,
        user: null as User | null,
        metrics: {} as Record<string, number>,
        orders: [] as Order[],
        productTypes: [] as ProductType[],
        productMappings: [] as ProductMapping[],
        issues: [] as Issue[],
        requiredActions: [] as RequiredAction[],
        notificationSubscriptions: [] as NotificationSubscription[],
    }),
    getters: {
        variants(state) {
            return state.productTypes.flatMap((type) =>
                type.variants.map((variant) => ({ ...variant, product_type: type })),
            );
        },
        openTickets(state) {
            return state.issues.filter((issue) => issue.type === 'ticket');
        },
        openClaims(state) {
            return state.issues.filter((issue) => issue.type === 'claim');
        },
    },
    actions: {
        async load() {
            this.loading = true;
            const payload = await request<PortalPayload>('/api/portal');
            this.tenant = payload.tenant;
            this.user = payload.user;
            this.metrics = payload.metrics;
            this.orders = payload.orders;
            this.productTypes = payload.productTypes;
            this.productMappings = payload.productMappings;
            this.issues = payload.issues;
            this.requiredActions = payload.requiredActions;
            this.notificationSubscriptions = payload.notificationSubscriptions;
            this.loaded = true;
            this.loading = false;
        },
        async createOrder(payload: Record<string, unknown>) {
            const order = await request<Order>('/api/orders', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.orders.unshift(order);
            await this.load();
            return order;
        },
        async previewImport(csv: string) {
            return request<ImportPreview>('/api/orders/imports/preview', {
                method: 'POST',
                body: JSON.stringify({ csv }),
            });
        },
        async createMapping(payload: Record<string, unknown>) {
            const mapping = await request<ProductMapping>('/api/product-mappings', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.productMappings.unshift(mapping);
            await this.load();
            return mapping;
        },
        async createIssue(type: 'ticket' | 'claim', payload: Record<string, unknown>) {
            const issue = await request<Issue>(`/api/issues/${type}`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.issues.unshift(issue);
            await this.load();
            return issue;
        },
        async updateSubscription(subscription: NotificationSubscription) {
            const updated = await request<NotificationSubscription>(`/api/notifications/subscriptions/${subscription.id}`, {
                method: 'PATCH',
                body: JSON.stringify({
                    email: subscription.email,
                    is_subscribed: subscription.is_subscribed,
                }),
            });
            const index = this.notificationSubscriptions.findIndex((item) => item.id === updated.id);
            if (index >= 0) {
                this.notificationSubscriptions[index] = updated;
            }
        },
    },
});
