import { createRouter, createWebHistory } from 'vue-router';

import DashboardView from '@/views/DashboardView.vue';
import OrdersListView from '@/views/orders/OrdersListView.vue';
import NewOrderView from '@/views/orders/NewOrderView.vue';
import ImportOrdersView from '@/views/orders/ImportOrdersView.vue';
import OrderDetailView from '@/views/orders/OrderDetailView.vue';
import ProductMappingsView from '@/views/products/ProductMappingsView.vue';
import IssuesView from '@/views/issues/IssuesView.vue';
import SettingsView from '@/views/settings/SettingsView.vue';

export default createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'dashboard', component: DashboardView },
        { path: '/orders', name: 'orders', component: OrdersListView },
        { path: '/orders/new', name: 'orders.new', component: NewOrderView },
        { path: '/orders/import', name: 'orders.import', component: ImportOrdersView },
        { path: '/orders/:uuid', name: 'orders.show', component: OrderDetailView, props: true },
        { path: '/products/mappings', name: 'products.mappings', component: ProductMappingsView },
        { path: '/issues/tickets', name: 'issues.tickets', component: IssuesView, props: { mode: 'tickets' } },
        { path: '/issues/claims', name: 'issues.claims', component: IssuesView, props: { mode: 'claims' } },
        { path: '/issues/actions', name: 'issues.actions', component: IssuesView, props: { mode: 'actions' } },
        { path: '/settings', name: 'settings', component: SettingsView },
    ],
});
