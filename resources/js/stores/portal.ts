import { defineStore } from 'pinia';

import type {
    AuthPayload,
    ImportBatch,
    ImportPreview,
    Issue,
    NotificationLog,
    MappingRule,
    MappingMutationResult,
    MediaFile,
    Payment,
    NotificationSubscription,
    Order,
    OrdersResponse,
    PortalPayload,
    ProductOption,
    ProductMapping,
    MappingSimulation,
    ProductType,
    RequiredAction,
    SavedView,
    Tenant,
    User,
    UserInvite,
} from '@/types/portal';

export class ApiError extends Error {
    constructor(message: string, public status: number, public errors: Record<string, string[]> = {}) {
        super(message);
    }
}

function csrfToken() {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const isFormData = options.body instanceof FormData;
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers ?? {}),
        },
        ...options,
    });

    if (!response.ok) {
        const body = await response.json().catch(() => null) as { message?: string; errors?: Record<string, string[]> } | null;
        throw new ApiError(body?.message ?? `Request failed with ${response.status}`, response.status, body?.errors ?? {});
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return response.json() as Promise<T>;
}

function applyAuthState(state: ReturnType<typeof usePortalStore>, payload: AuthPayload) {
    state.tenant = payload.tenant;
    state.user = payload.user;
    state.abilities = payload.abilities;
    state.authenticated = Boolean(payload.user);
}

export const usePortalStore = defineStore('portal', {
    state: () => ({
        authChecked: false,
        authenticated: false,
        loaded: false,
        loading: false,
        abilities: [] as string[],
        tenant: null as Tenant | null,
        user: null as User | null,
        metrics: {} as Record<string, number>,
        orders: [] as Order[],
        orderList: [] as Order[],
        ordersMeta: null as OrdersResponse['meta'] | null,
        ordersSummary: {} as Record<string, number>,
        savedViews: [] as SavedView[],
        productTypes: [] as ProductType[],
        productMappings: [] as ProductMapping[],
        issues: [] as Issue[],
        requiredActions: [] as RequiredAction[],
        notificationSubscriptions: [] as NotificationSubscription[],
        notificationLogs: [] as NotificationLog[],
        users: [] as User[],
        userInvites: [] as UserInvite[],
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
        can: (state) => (permission: string) => state.abilities.includes(permission),
    },
    actions: {
        async checkSession() {
            try {
                const payload = await request<AuthPayload>('/api/auth/session');
                applyAuthState(this, payload);
            } catch (error) {
                if (error instanceof ApiError && [401, 403].includes(error.status)) {
                    this.authenticated = false;
                    this.tenant = null;
                    this.user = null;
                    this.abilities = [];
                } else {
                    throw error;
                }
            } finally {
                this.authChecked = true;
            }
        },
        async login(payload: { email: string; password: string; remember: boolean }) {
            const response = await request<AuthPayload>('/api/auth/login', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            applyAuthState(this, response);
            this.authChecked = true;
            await this.load();
        },
        async logout() {
            await request<{ message: string }>('/api/auth/logout', { method: 'POST' });
            this.$reset();
            this.authChecked = true;
        },
        async forgotPassword(email: string) {
            return request<{ message: string }>('/api/auth/forgot-password', {
                method: 'POST',
                body: JSON.stringify({ email }),
            });
        },
        async load() {
            if (!this.authenticated) {
                await this.checkSession();
            }

            if (!this.authenticated) {
                return;
            }

            this.loading = true;
            try {
                const payload = await request<PortalPayload>('/api/workspace');
                this.tenant = payload.tenant;
                this.user = payload.user;
                this.abilities = payload.abilities;
                this.metrics = payload.metrics;
                this.orders = payload.orders;
                this.orderList = payload.orders;
                this.savedViews = payload.savedViews ?? [];
                this.productTypes = payload.productTypes;
                this.productMappings = payload.productMappings;
                this.issues = payload.issues;
                this.requiredActions = payload.requiredActions;
                this.notificationSubscriptions = payload.notificationSubscriptions;
                this.users = payload.users;
                this.userInvites = payload.userInvites;
                this.loaded = true;
            } finally {
                this.loading = false;
            }
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
        async createPaymentIntent(order: Order, options: { force_new_intent?: boolean } = {}) {
            const payload = await request<{
                order: Order;
                payment: Payment;
                client_secret: string;
            }>(`/api/orders/${order.uuid}/payment/intent`, {
                method: 'POST',
                body: JSON.stringify(options),
            });

            this.replaceOrder(payload.order);
            return payload;
        },
        async confirmPayment(order: Order, payload: { payment_intent_id: string }) {
            const result = await request<{
                order: Order;
                result: {
                    payment_status: string;
                    order_status: string;
                    payment_intent_status: string;
                    requires_action: boolean;
                };
            }>(`/api/orders/${order.uuid}/payment/confirm`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            this.replaceOrder(result.order);
            await this.load();
            return result;
        },
        async fetchOrders(params: Record<string, string | number | null | undefined> = {}) {
            const search = new URLSearchParams();
            Object.entries(params).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    search.set(key, String(value));
                }
            });

            const response = await request<OrdersResponse>(`/api/orders?${search.toString()}`);
            this.orderList = response.data;
            this.ordersMeta = response.meta;
            this.ordersSummary = response.summary;
            return response;
        },
        async fetchOrder(uuid: string) {
            const order = await request<Order>(`/api/orders/${uuid}`);
            const index = this.orders.findIndex((item) => item.uuid === uuid);
            if (index >= 0) {
                this.orders[index] = order;
            } else {
                this.orders.unshift(order);
            }

            return order;
        },
        async updateOrderAddress(order: Order, payload: Record<string, unknown>) {
            const updated = await request<Order>(`/api/orders/${order.uuid}/address`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
            this.replaceOrder(updated);
            return updated;
        },
        async updateOrderNotes(order: Order, notes: string | null) {
            const updated = await request<Order>(`/api/orders/${order.uuid}/notes`, {
                method: 'PATCH',
                body: JSON.stringify({ notes }),
            });
            this.replaceOrder(updated);
            return updated;
        },
        async transitionOrder(order: Order, payload: Record<string, unknown>) {
            const result = await request<{ order: Order; allowed_next_statuses: string[] }>(`/api/orders/${order.uuid}/transition`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.replaceOrder(result.order);
            await this.load();
            return result;
        },
        async exportOrders(params: Record<string, string | number | null | undefined> = {}) {
            const search = new URLSearchParams();
            Object.entries(params).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    search.set(key, String(value));
                }
            });

            const response = await fetch(`/api/orders/export?${search.toString()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'text/csv', 'X-CSRF-TOKEN': csrfToken() },
            });

            if (!response.ok) {
                throw new ApiError(`Export failed with ${response.status}`, response.status);
            }

            return response.blob();
        },
        async saveOrderView(payload: { name: string; filters: Record<string, string>; sort: Record<string, string>; is_default?: boolean }) {
            const view = await request<SavedView>('/api/orders/saved-views', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            const index = this.savedViews.findIndex((item) => item.id === view.id);
            if (index >= 0) {
                this.savedViews[index] = view;
            } else {
                this.savedViews.unshift(view);
            }
            return view;
        },
        async deleteOrderView(view: SavedView) {
            await request<{ message: string }>(`/api/orders/saved-views/${view.id}`, { method: 'DELETE' });
            this.savedViews = this.savedViews.filter((item) => item.id !== view.id);
        },
        replaceOrder(order: Order) {
            const index = this.orders.findIndex((item) => item.id === order.id);
            if (index >= 0) {
                this.orders[index] = order;
            } else {
                this.orders.unshift(order);
            }
            const listIndex = this.orderList.findIndex((item) => item.id === order.id);
            if (listIndex >= 0) {
                this.orderList[listIndex] = order;
            }
        },
        async previewImport(csv: string) {
            return request<ImportPreview>('/api/orders/imports/preview', {
                method: 'POST',
                body: JSON.stringify({ csv }),
            });
        },
        async importHistory() {
            return request<ImportBatch[]>('/api/orders/imports');
        },
        async commitImport(importId: number) {
            const result = await request<{ import: ImportBatch; created_orders: number; skipped_rows: number }>(`/api/orders/imports/${importId}/commit`, {
                method: 'POST',
            });
            await this.load();
            return result;
        },
        async createMapping(payload: Record<string, unknown>) {
            const result = await request<MappingMutationResult>('/api/product-mappings', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await this.load();
            return result;
        },
        async updateMapping(mapping: ProductMapping, payload: Record<string, unknown>) {
            const result = await request<MappingMutationResult>(`/api/product-mappings/${mapping.id}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
            await this.load();
            return result;
        },
        async deleteMapping(mapping: ProductMapping) {
            await request<{ message: string }>(`/api/product-mappings/${mapping.id}`, { method: 'DELETE' });
            await this.load();
        },
        async simulateMapping(payload: Record<string, unknown>) {
            return request<MappingSimulation>('/api/product-mappings/simulate', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },
        async detectMappingConflicts(payload: Record<string, unknown>) {
            return request<{ conflicts: Array<{ id: number; name: string; rules: MappingRule[] }> }>('/api/product-mappings/conflicts', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        },
        async createProductType(payload: Record<string, unknown>) {
            await request<ProductType>('/api/products/types', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await this.load();
        },
        async updateProductType(productType: ProductType, payload: Record<string, unknown>) {
            await request<ProductType>(`/api/products/types/${productType.id}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
            await this.load();
        },
        async deleteProductType(productType: ProductType) {
            await request<{ message: string }>(`/api/products/types/${productType.id}`, { method: 'DELETE' });
            await this.load();
        },
        async createProductVariant(productType: ProductType, payload: Record<string, unknown>) {
            await request<ProductType>(`/api/products/types/${productType.id}/variants`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await this.load();
        },
        async updateProductVariant(variantId: number, payload: Record<string, unknown>) {
            await request<ProductType>(`/api/products/variants/${variantId}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
            await this.load();
        },
        async deleteProductVariant(variantId: number) {
            await request<{ message: string }>(`/api/products/variants/${variantId}`, { method: 'DELETE' });
            await this.load();
        },
        async createProductOption(productType: ProductType, payload: Record<string, unknown>) {
            await request<ProductOption>(`/api/products/types/${productType.id}/options`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await this.load();
        },
        async updateProductOption(optionId: number, payload: Record<string, unknown>) {
            await request<ProductOption>(`/api/products/options/${optionId}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
            await this.load();
        },
        async deleteProductOption(optionId: number) {
            await request<{ message: string }>(`/api/products/options/${optionId}`, { method: 'DELETE' });
            await this.load();
        },
        async uploadFile(file: File, collection: string) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('collection', collection);

            return request<MediaFile>('/api/uploads', {
                method: 'POST',
                body: formData,
            });
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
        replaceIssue(issue: Issue) {
            const index = this.issues.findIndex((item) => item.id === issue.id);
            if (index >= 0) {
                this.issues[index] = issue;
            } else {
                this.issues.unshift(issue);
            }
        },
        async fetchIssue(issue: Issue) {
            const updated = await request<Issue>(`/api/issues/${issue.id}`);
            this.replaceIssue(updated);
            return updated;
        },
        async updateIssue(issue: Issue, payload: { status?: string; priority?: string; assigned_to_id?: number | null }) {
            const updated = await request<Issue>(`/api/issues/${issue.id}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
            this.replaceIssue(updated);
            await this.load();
            return updated;
        },
        async addIssueComment(issue: Issue, payload: { body: string; attachments?: unknown[]; internal?: boolean }) {
            const updated = await request<Issue>(`/api/issues/${issue.id}/comments`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.replaceIssue(updated);
            await this.load();
            return updated;
        },
        async resolveClaim(
            issue: Issue,
            payload: {
                decision: 'credit' | 'refund' | 'reprint' | 'reject';
                amount_cents?: number;
                currency?: string;
                finance_reference?: string | null;
                production_outcome?: string | null;
                notes?: string | null;
                evidence_files?: unknown[];
            },
        ) {
            const updated = await request<Issue>(`/api/claims/${issue.id}/resolution`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.replaceIssue(updated);
            await this.load();
            return updated;
        },
        async markIssueRead(issue: Issue) {
            const updated = await request<Issue>(`/api/issues/${issue.id}/read`, { method: 'POST' });
            this.replaceIssue(updated);
            await this.load();
            return updated;
        },
        replaceRequiredAction(action: RequiredAction) {
            const index = this.requiredActions.findIndex((item) => item.id === action.id);
            if (index >= 0) {
                this.requiredActions[index] = action;
            } else {
                this.requiredActions.unshift(action);
            }
        },
        async fetchRequiredActions() {
            this.requiredActions = await request<RequiredAction[]>('/api/required-actions');
            return this.requiredActions;
        },
        async addRequiredActionComment(action: RequiredAction, payload: { body: string; internal?: boolean }) {
            const updated = await request<RequiredAction>(`/api/required-actions/${action.id}/comments`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.replaceRequiredAction(updated);
            return updated;
        },
        async resolveRequiredAction(action: RequiredAction, payload: { resolution?: Record<string, unknown>; comment?: string | null }) {
            const updated = await request<RequiredAction>(`/api/required-actions/${action.id}/resolve`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.replaceRequiredAction(updated);
            await this.load();
            return updated;
        },
        async reopenRequiredAction(action: RequiredAction, payload: { comment?: string | null } = {}) {
            const updated = await request<RequiredAction>(`/api/required-actions/${action.id}/reopen`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.replaceRequiredAction(updated);
            await this.load();
            return updated;
        },
        async escalateRequiredAction(action: RequiredAction, payload: { comment?: string | null; priority?: string } = {}) {
            const updated = await request<RequiredAction>(`/api/required-actions/${action.id}/escalate`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            this.replaceRequiredAction(updated);
            await this.load();
            return updated;
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
        async loadNotificationLogs() {
            this.notificationLogs = await request<NotificationLog[]>('/api/notifications/logs');
            return this.notificationLogs;
        },
        async previewNotificationLog(log: NotificationLog) {
            return request<{
                subject: string;
                body_html: string;
                body_text: string | null;
                status: string;
                attempts: number;
                error_message: string | null;
                max_attempts: number;
            }>(`/api/notifications/logs/${log.id}`);
        },
        async retryNotificationLog(log: NotificationLog, payload: { recipient_email?: string } = {}) {
            const updated = await request<NotificationLog>(`/api/notifications/logs/${log.id}/retry`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            const index = this.notificationLogs.findIndex((item) => item.id === updated.id);
            if (index >= 0) {
                this.notificationLogs[index] = updated;
            }
            return updated;
        },
        async inviteUser(payload: { name: string; email: string; role: User['role'] }) {
            await request<{ user: User; invite: UserInvite }>('/api/users/invites', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await this.load();
        },
        async updateUser(user: User, payload: Partial<Pick<User, 'name' | 'email' | 'role' | 'active'>>) {
            const updated = await request<User>(`/api/users/${user.id}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
            const index = this.users.findIndex((item) => item.id === updated.id);
            if (index >= 0) {
                this.users[index] = updated;
            }
            if (this.user?.id === updated.id) {
                this.user = updated;
            }
        },
    },
});
