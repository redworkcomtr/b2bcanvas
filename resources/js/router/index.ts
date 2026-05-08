import { createRouter, createWebHistory } from 'vue-router';

import DashboardView from '@/views/DashboardView.vue';
import OrdersListView from '@/views/orders/OrdersListView.vue';
import NewOrderView from '@/views/orders/NewOrderView.vue';
import ImportOrdersView from '@/views/orders/ImportOrdersView.vue';
import OrderDetailView from '@/views/orders/OrderDetailView.vue';
import ProductMappingsView from '@/views/products/ProductMappingsView.vue';
import IssuesView from '@/views/issues/IssuesView.vue';
import SettingsView from '@/views/settings/SettingsView.vue';
import LoginView from '@/views/auth/LoginView.vue';
import ForgotPasswordView from '@/views/auth/ForgotPasswordView.vue';

export default createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/login', name: 'login', component: LoginView, meta: { guest: true } },
        { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView, meta: { guest: true } },
        { path: '/', name: 'dashboard', component: DashboardView },
        { path: '/orders', name: 'orders', component: OrdersListView },
        { path: '/orders/new', name: 'orders.new', component: NewOrderView, meta: { permission: 'manage_orders' } },
        { path: '/orders/import', name: 'orders.import', component: ImportOrdersView, meta: { permission: 'manage_orders' } },
        { path: '/orders/:uuid', name: 'orders.show', component: OrderDetailView, props: true },
        { path: '/products/mappings', name: 'products.mappings', component: ProductMappingsView, meta: { permission: 'manage_mappings' } },
        { path: '/issues/tickets', name: 'issues.tickets', component: IssuesView, props: { mode: 'tickets' }, meta: { permission: 'manage_issues' } },
        { path: '/issues/claims', name: 'issues.claims', component: IssuesView, props: { mode: 'claims' }, meta: { permission: 'manage_issues' } },
        { path: '/issues/actions', name: 'issues.actions', component: IssuesView, props: { mode: 'actions' }, meta: { permission: 'manage_mappings' } },
        { path: '/settings', name: 'settings', component: SettingsView },
    ],
});
