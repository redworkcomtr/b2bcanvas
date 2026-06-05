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
const activeActionStatuses = ['open', 'in_progress', 'escalated'];

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
    ...store.requiredActions.filter((action) => activeActionStatuses.includes(action.status)).map((action) => ({
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
    <div v-else class="min-h-screen bg-[#f9f9f9] lg:h-screen lg:overflow-hidden lg:p-4">
        <div class="flex min-h-screen w-full gap-4 lg:h-full lg:min-h-0">
            <aside
                :class="[
                    'hidden h-full shrink-0 flex-col overflow-hidden bg-[#f9f9f9] transition-all lg:flex',
                    sidebarOpen ? 'w-[260px]' : 'w-20',
                ]"
            >
                <div class="mb-6 flex items-center gap-3 px-3 py-3">
                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-black text-xs font-bold text-white">
                        BC
                    </div>
                    <div v-if="sidebarOpen" class="min-w-0">
                        <p class="truncate text-[14px] font-bold leading-tight text-[#18181b]">{{ store.tenant?.name ?? 'B2B Canvas' }}</p>
                        <p class="truncate text-[12px] leading-tight text-[#71717a]">Enterprise</p>
                    </div>
                </div>

                <nav class="sidebar-scroll flex-1 overflow-y-auto pr-1">
                    <div v-for="group in ['Workspace', 'Orders', 'Products', 'Issues', 'Admin']" :key="group" class="mb-6">
                        <p v-if="sidebarOpen" class="mb-2 px-3 text-[11px] font-semibold uppercase text-[#71717a]">{{ group }}</p>
                        <div class="space-y-0.5">
                            <RouterLink
                                v-for="item in availableNav.filter((entry) => entry.group === group)"
                                :key="item.to"
                                :to="item.to"
                                :title="item.label"
                                :class="[
                                    'flex items-center rounded-lg px-3 py-2 text-[14px] font-medium transition-colors',
                                    route.path === item.to ? 'bg-zinc-200/60 text-[#18181b]' : 'text-[#18181b] hover:bg-zinc-200/50',
                                    sidebarOpen ? 'justify-between gap-3' : 'justify-center',
                                ]"
                            >
                                <span class="flex min-w-0 items-center gap-3">
                                    <component :is="item.icon" class="h-5 w-5 shrink-0 stroke-[1.6]" />
                                    <span v-if="sidebarOpen" class="truncate">{{ item.label }}</span>
                                </span>
                                <span
                                    v-if="sidebarOpen && item.badge"
                                    class="ml-auto rounded-md bg-zinc-200/70 px-1.5 py-0.5 text-[11px] font-semibold text-[#71717a]"
                                >
                                    {{ item.badge }}
                                </span>
                            </RouterLink>
                        </div>
                    </div>
                </nav>

                <Dropdown>
                    <template #trigger>
                        <button class="focus-ring mt-auto flex w-full items-center justify-between rounded-xl p-2 text-left transition-colors hover:bg-zinc-200/50">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-zinc-200 text-[12px] font-bold text-[#18181b]">
                                    {{ initials(store.user?.name) }}
                                </div>
                                <div v-if="sidebarOpen" class="min-w-0">
                                    <p class="truncate text-[13px] font-semibold leading-tight text-[#18181b]">{{ store.user?.name ?? 'User' }}</p>
                                    <p class="truncate text-[11px] leading-tight text-[#71717a]">{{ store.user?.email ?? store.user?.role }}</p>
                                </div>
                            </div>
                            <ChevronRight v-if="sidebarOpen" class="h-4 w-4 shrink-0 text-zinc-400" />
                        </button>
                    </template>
                    <template #default="{ close }">
                        <div class="w-64 p-2">
                            <div class="px-2 py-2">
                                <p class="truncate text-sm font-bold text-[#18181b]">{{ store.user?.name }}</p>
                                <p class="truncate text-xs text-[#71717a]">{{ store.user?.email }}</p>
                                <Badge class="mt-2" tone="neutral">{{ store.user?.role }}</Badge>
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
            </aside>

            <Sheet v-model:open="mobileNavOpen" title="Navigation">
                <nav class="grid gap-1">
                    <RouterLink
                        v-for="item in availableNav"
                        :key="item.to"
                        :to="item.to"
                        class="flex h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium text-[#18181b] hover:bg-zinc-200/50"
                        @click="mobileNavOpen = false"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        {{ item.label }}
                        <Badge v-if="item.badge" tone="warning" class="ml-auto">{{ item.badge }}</Badge>
                    </RouterLink>
                </nav>
            </Sheet>

            <section class="flex min-w-0 flex-1 flex-col overflow-hidden bg-white lg:rounded-[2rem] lg:border lg:border-zinc-200 lg:shadow-sm">
                <header class="flex h-[60px] shrink-0 items-center px-4 sm:px-6">
                    <div class="flex w-full items-center justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <Button class="lg:hidden" variant="ghost" size="icon" aria-label="Open navigation" @click="mobileNavOpen = true">
                                <Menu class="h-5 w-5" />
                            </Button>
                            <Button class="hidden lg:inline-flex" variant="ghost" size="icon" aria-label="Toggle navigation" @click="sidebarOpen = !sidebarOpen">
                                <Menu class="h-5 w-5" />
                            </Button>
                            <div class="hidden h-4 w-px bg-zinc-200 sm:block" />
                            <div class="min-w-0 text-[14px]">
                                <div class="hidden items-center gap-2 text-[#71717a] sm:flex">
                                    <template v-for="(crumb, index) in breadcrumbs" :key="crumb + index">
                                        <span :class="index === breadcrumbs.length - 1 ? 'font-semibold text-[#18181b]' : ''">{{ crumb }}</span>
                                        <ChevronRight v-if="index < breadcrumbs.length - 1" class="h-4 w-4 text-zinc-300" />
                                    </template>
                                </div>
                                <h1 class="truncate text-[14px] font-semibold text-[#18181b] sm:hidden">{{ breadcrumbs[breadcrumbs.length - 1] }}</h1>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 sm:gap-3">
                            <button
                                class="focus-ring hidden h-9 w-72 items-center gap-2 rounded-lg border border-zinc-200/70 bg-[#f9f9f9] px-3 text-left text-[14px] text-[#71717a] xl:flex"
                                @click="commandOpen = true"
                            >
                                <Search class="h-4 w-4" />
                                <span class="flex-1">Search orders, issues...</span>
                                <kbd class="rounded-md border border-zinc-200 bg-white px-1.5 py-0.5 text-[11px] font-bold text-zinc-400">⌘K</kbd>
                            </button>

                            <Button class="xl:hidden" variant="outline" size="icon" aria-label="Open search" @click="commandOpen = true">
                                <Search class="h-4 w-4" />
                            </Button>

                            <Dropdown>
                                <template #trigger>
                                    <Button variant="outline" size="icon" aria-label="Open notifications" title="Notifications">
                                        <Bell class="h-4 w-4" />
                                        <span v-if="notifications.length" class="absolute -mr-6 -mt-6 grid h-5 min-w-5 place-items-center rounded-full bg-black px-1 text-[11px] font-bold text-white">
                                            {{ notifications.length }}
                                        </span>
                                    </Button>
                                </template>
                                <template #default="{ close }">
                                    <div class="max-w-96 p-2">
                                        <p class="px-2 py-1 text-xs font-bold text-[#71717a]">Notifications</p>
                                        <button
                                            v-for="item in notifications"
                                            :key="item.title + item.description"
                                            class="focus-ring w-full rounded-md p-3 text-left hover:bg-zinc-100"
                                            @click="go(item.to); close()"
                                        >
                                            <div class="flex items-center gap-2">
                                                <Badge :tone="item.tone">{{ item.tone }}</Badge>
                                                <p class="text-sm font-bold text-[#18181b]">{{ item.title }}</p>
                                            </div>
                                            <p class="mt-1 line-clamp-2 text-sm text-[#71717a]">{{ item.description }}</p>
                                        </button>
                                        <p v-if="notifications.length === 0" class="px-3 py-6 text-center text-sm text-[#71717a]">No open notifications.</p>
                                    </div>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </header>

                <main class="sidebar-scroll flex-1 overflow-y-auto p-5 lg:p-8">
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
            </section>

            <CommandPalette v-model:open="commandOpen" :items="commandItems" @select="go" />
        </div>
    </div>
</template>
