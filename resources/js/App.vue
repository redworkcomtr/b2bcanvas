<script setup lang="ts">
import {
    Bell,
    Box,
    ClipboardList,
    FileWarning,
    Home,
    LifeBuoy,
    Map,
    Menu,
    PackagePlus,
    Settings,
    Upload,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';

import Button from '@/components/ui/Button.vue';
import { usePortalStore } from '@/stores/portal';

const store = usePortalStore();
const route = useRoute();
const sidebarOpen = ref(true);

const nav = computed(() => [
    { label: 'Dashboard', to: '/', icon: Home },
    { label: 'Orders List', to: '/orders', icon: ClipboardList },
    { label: 'New Order', to: '/orders/new', icon: PackagePlus },
    { label: 'Import Orders', to: '/orders/import', icon: Upload },
    { label: 'Product Mappings', to: '/products/mappings', icon: Map },
    { label: 'Tickets', to: '/issues/tickets', icon: LifeBuoy, badge: store.metrics.tickets },
    { label: 'Claims', to: '/issues/claims', icon: FileWarning, badge: store.metrics.claims },
    { label: 'Required Actions', to: '/issues/actions', icon: Bell, badge: store.metrics.requiredActions },
    { label: 'Settings', to: '/settings', icon: Settings },
]);

onMounted(() => {
    if (!store.loaded) {
        store.load();
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 border-r border-slate-200 bg-white transition-all',
                sidebarOpen ? 'w-72' : 'w-20',
            ]"
        >
            <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-4">
                <div class="grid h-10 w-10 place-items-center rounded-md bg-slate-950 text-sm font-bold text-white">
                    BC
                </div>
                <div v-if="sidebarOpen" class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-950">{{ store.tenant?.name ?? 'B2B Canvas' }}</p>
                    <p class="truncate text-xs text-slate-500">{{ store.user?.email ?? 'Loading workspace' }}</p>
                </div>
            </div>

            <nav class="grid gap-1 p-3">
                <RouterLink
                    v-for="item in nav"
                    :key="item.to"
                    :to="item.to"
                    :class="[
                        'flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium transition',
                        route.path === item.to ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950',
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
            </nav>
        </aside>

        <div :class="['transition-all', sidebarOpen ? 'pl-72' : 'pl-20']">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-6 backdrop-blur">
                <div class="flex items-center gap-3">
                    <Button variant="ghost" size="icon" aria-label="Toggle navigation" @click="sidebarOpen = !sidebarOpen">
                        <Menu class="h-5 w-5" />
                    </Button>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Fulfillment Portal</p>
                        <h1 class="text-lg font-bold text-slate-950">B2B Canvas Operations</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600 md:flex">
                        <Box class="h-4 w-4 text-teal-700" />
                        {{ store.metrics.orders ?? 0 }} active orders
                    </div>
                    <RouterLink to="/settings">
                        <Button variant="outline" size="sm">
                            <Settings class="h-4 w-4" />
                            Settings
                        </Button>
                    </RouterLink>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1500px] p-6">
                <div v-if="store.loading && !store.loaded" class="grid min-h-[60vh] place-items-center">
                    <div class="rounded-lg border border-slate-200 bg-white px-6 py-5 text-sm font-medium text-slate-600 shadow-sm">
                        Loading portal workspace...
                    </div>
                </div>
                <RouterView v-else />
            </main>
        </div>
    </div>
</template>
