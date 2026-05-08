<script setup lang="ts">
import {
    Bell,
    ChevronRight,
    ClipboardList,
    FileWarning,
    Home,
    LifeBuoy,
    LogOut,
    Map,
    Menu,
    PackagePlus,
    Search,
    Settings,
    Upload,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';

import CommandPalette from '@/components/portal/CommandPalette.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Dropdown from '@/components/ui/Dropdown.vue';
import Sheet from '@/components/ui/Sheet.vue';
import Skeleton from '@/components/ui/Skeleton.vue';
import { humanize, initials } from '@/lib/utils';
import { usePortalStore } from '@/stores/portal';

const store = usePortalStore();
const route = useRoute();
const router = useRouter();
const sidebarOpen = ref(true);
const mobileNavOpen = ref(false);
const commandOpen = ref(false);

const nav = computed(() => [
    { label: 'Dashboard', to: '/', icon: Home, group: 'Workspace' },
    { label: 'Orders List', to: '/orders', icon: ClipboardList, group: 'Orders' },
    { label: 'New Order', to: '/orders/new', icon: PackagePlus, group: 'Orders', permission: 'manage_orders' },
    { label: 'Import Orders', to: '/orders/import', icon: Upload, group: 'Orders', permission: 'manage_orders' },
    { label: 'Product Catalog', to: '/products/catalog', icon: PackagePlus, group: 'Products', permission: 'manage_catalog' },
    { label: 'Product Mappings', to: '/products/mappings', icon: Map, group: 'Products', permission: 'manage_mappings' },
    { label: 'Tickets', to: '/issues/tickets', icon: LifeBuoy, badge: store.metrics.tickets, group: 'Issues', permission: 'manage_issues' },
    { label: 'Claims', to: '/issues/claims', icon: FileWarning, badge: store.metrics.claims, group: 'Issues', permission: 'manage_issues' },
    { label: 'Required Actions', to: '/issues/actions', icon: Bell, badge: store.metrics.requiredActions, group: 'Issues', permission: 'manage_mappings' },
    { label: 'Settings', to: '/settings', icon: Settings, group: 'Admin' },
]);

const availableNav = computed(() => nav.value.filter((item) => !item.permission || store.can(item.permission)));
const breadcrumbs = computed(() => {
    const match = availableNav.value.find((item) => item.to === route.path);
    if (match) {
        return ['Portal', match.group, match.label];
    }

    if (route.path.startsWith('/orders/')) {
        return ['Portal', 'Orders', 'Order Detail'];
    }

    return ['Portal'];
});

const commandItems = computed(() => [
    ...availableNav.value.map((item) => ({
        label: item.label,
        caption: item.group,
        to: item.to,
        group: 'Navigation',
    })),
    ...store.orders.map((order) => ({
        label: order.order_number,
        caption: `${order.customer_name} · ${humanize(order.status)}`,
        to: `/orders/${order.uuid}`,
        group: 'Orders',
    })),
    ...store.issues.map((issue) => ({
        label: `${issue.type === 'ticket' ? 'Ticket' : 'Claim'} #${issue.id}`,
        caption: issue.description,
        to: issue.type === 'ticket' ? '/issues/tickets' : '/issues/claims',
        group: 'Issues',
    })),
]);

const notifications = computed(() => [
    ...store.requiredActions.filter((action) => action.status === 'open').map((action) => ({
        title: action.title,
        description: action.description,
        to: '/issues/actions',
        tone: 'warning' as const,
    })),
    ...store.issues.filter((issue) => issue.unread_notes_count > 0).map((issue) => ({
        title: `${issue.unread_notes_count} unread ${issue.type} note`,
        description: issue.description,
        to: issue.type === 'ticket' ? '/issues/tickets' : '/issues/claims',
        tone: 'info' as const,
    })),
]);

function go(to: string) {
    router.push(to);
}

async function logout() {
    await store.logout();
    router.push('/login');
}

function onKeydown(event: KeyboardEvent) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        commandOpen.value = true;
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    if (!route.meta.guest && store.authenticated && !store.loaded) {
        store.load();
    }
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <RouterView v-if="route.meta.guest" />
    <div v-else class="min-h-screen bg-[hsl(var(--background))]">
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 hidden border-r border-slate-200 bg-white transition-all lg:block',
                sidebarOpen ? 'w-72' : 'w-20',
            ]"
        >
            <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-4">
                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-slate-950 text-sm font-bold text-white">
                    BC
                </div>
                <div v-if="sidebarOpen" class="min-w-0">
                    <p class="truncate text-sm font-bold text-slate-950">{{ store.tenant?.name ?? 'B2B Canvas' }}</p>
                    <p class="truncate text-xs text-slate-500">{{ store.user?.email ?? 'Loading workspace' }}</p>
                </div>
            </div>

            <nav class="scrollbar-thin h-[calc(100vh-64px)] overflow-auto p-3">
                <div v-for="group in ['Workspace', 'Orders', 'Products', 'Issues', 'Admin']" :key="group" class="mb-4">
                    <p v-if="sidebarOpen" class="mb-1 px-3 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ group }}</p>
                    <div class="grid gap-1">
                        <RouterLink
                            v-for="item in availableNav.filter((entry) => entry.group === group)"
                            :key="item.to"
                            :to="item.to"
                            :title="item.label"
                            :class="[
                                'flex h-10 items-center gap-3 rounded-md px-3 text-sm font-semibold transition',
                                route.path === item.to ? 'bg-teal-50 text-teal-800 ring-1 ring-teal-100' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950',
                            ]"
                        >
                            <component :is="item.icon" class="h-4 w-4 shrink-0" />
                            <span v-if="sidebarOpen" class="truncate">{{ item.label }}</span>
                            <span
                                v-if="sidebarOpen && item.badge"
                                class="ml-auto rounded-full bg-orange-100 px-2 py-0.5 text-xs font-bold text-orange-700"
                            >
                                {{ item.badge }}
                            </span>
                        </RouterLink>
                    </div>
                </div>
            </nav>
        </aside>

        <Sheet v-model:open="mobileNavOpen" title="Navigation">
            <nav class="grid gap-1">
                <RouterLink
                    v-for="item in availableNav"
                    :key="item.to"
                    :to="item.to"
                    class="flex h-11 items-center gap-3 rounded-md px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    @click="mobileNavOpen = false"
                >
                    <component :is="item.icon" class="h-4 w-4" />
                    {{ item.label }}
                    <Badge v-if="item.badge" tone="warning" class="ml-auto">{{ item.badge }}</Badge>
                </RouterLink>
            </nav>
        </Sheet>

        <div :class="[
            'min-w-0 transition-all',
            sidebarOpen ? 'lg:ml-72 lg:w-[calc(100%-18rem)]' : 'lg:ml-20 lg:w-[calc(100%-5rem)]',
        ]">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <Button class="lg:hidden" variant="ghost" size="icon" aria-label="Open navigation" @click="mobileNavOpen = true">
                            <Menu class="h-5 w-5" />
                        </Button>
                        <Button class="hidden lg:inline-flex" variant="ghost" size="icon" aria-label="Toggle navigation" @click="sidebarOpen = !sidebarOpen">
                            <Menu class="h-5 w-5" />
                        </Button>
                        <div class="min-w-0">
                            <div class="hidden items-center gap-1 text-xs font-semibold text-slate-500 sm:flex">
                                <template v-for="(crumb, index) in breadcrumbs" :key="crumb + index">
                                    <span>{{ crumb }}</span>
                                    <ChevronRight v-if="index < breadcrumbs.length - 1" class="h-3 w-3" />
                                </template>
                            </div>
                            <h1 class="truncate text-lg font-bold text-slate-950">{{ breadcrumbs[breadcrumbs.length - 1] }}</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 sm:gap-3">
                        <button
                            class="focus-ring hidden h-10 w-72 items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 text-left text-sm text-slate-500 shadow-sm xl:flex"
                            @click="commandOpen = true"
                        >
                            <Search class="h-4 w-4" />
                            <span class="flex-1">Search orders, issues...</span>
                            <kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[11px] font-bold text-slate-400">⌘K</kbd>
                        </button>

                        <Button class="xl:hidden" variant="outline" size="icon" aria-label="Open search" @click="commandOpen = true">
                            <Search class="h-4 w-4" />
                        </Button>

                        <Dropdown>
                            <template #trigger>
                                <Button variant="outline" size="icon" aria-label="Open notifications" title="Notifications">
                                    <Bell class="h-4 w-4" />
                                    <span v-if="notifications.length" class="absolute -mr-6 -mt-6 grid h-5 min-w-5 place-items-center rounded-full bg-orange-600 px-1 text-[11px] font-bold text-white">
                                        {{ notifications.length }}
                                    </span>
                                </Button>
                            </template>
                            <template #default="{ close }">
                                <div class="max-w-96 p-2">
                                    <p class="px-2 py-1 text-xs font-bold uppercase tracking-wide text-slate-400">Notifications</p>
                                    <button
                                        v-for="item in notifications"
                                        :key="item.title + item.description"
                                        class="focus-ring w-full rounded-md p-3 text-left hover:bg-slate-50"
                                        @click="go(item.to); close()"
                                    >
                                        <div class="flex items-center gap-2">
                                            <Badge :tone="item.tone">{{ item.tone }}</Badge>
                                            <p class="text-sm font-bold text-slate-950">{{ item.title }}</p>
                                        </div>
                                        <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ item.description }}</p>
                                    </button>
                                    <p v-if="notifications.length === 0" class="px-3 py-6 text-center text-sm text-slate-500">No open notifications.</p>
                                </div>
                            </template>
                        </Dropdown>

                        <Dropdown>
                            <template #trigger>
                                <button class="focus-ring hidden items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 md:flex">
                                    <div class="grid h-7 w-7 place-items-center rounded bg-slate-950 text-xs font-bold text-white">
                                        {{ initials(store.user?.name) }}
                                    </div>
                                    <div class="min-w-0 pr-1 text-left">
                                        <p class="truncate text-xs font-bold text-slate-950">{{ store.user?.name ?? 'User' }}</p>
                                        <p class="truncate text-[11px] text-slate-500">{{ store.user?.role ?? 'loading' }}</p>
                                    </div>
                                </button>
                            </template>
                            <template #default="{ close }">
                                <div class="w-64 p-2">
                                    <div class="border-b border-slate-200 px-2 py-2">
                                        <p class="truncate text-sm font-bold text-slate-950">{{ store.user?.name }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ store.user?.email }}</p>
                                        <Badge class="mt-2" tone="info">{{ store.user?.role }}</Badge>
                                    </div>
                                    <button
                                        class="focus-ring mt-2 flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-semibold text-red-700 hover:bg-red-50"
                                        @click="close(); logout()"
                                    >
                                        <LogOut class="h-4 w-4" />
                                        Sign out
                                    </button>
                                </div>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1540px] p-4 sm:p-6">
                <div v-if="store.loading && !store.loaded" class="grid gap-4">
                    <Skeleton class="h-24" />
                    <div class="grid gap-4 md:grid-cols-3">
                        <Skeleton class="h-36" />
                        <Skeleton class="h-36" />
                        <Skeleton class="h-36" />
                    </div>
                </div>
                <RouterView v-else />
            </main>
        </div>

        <CommandPalette v-model:open="commandOpen" :items="commandItems" @select="go" />
    </div>
</template>
