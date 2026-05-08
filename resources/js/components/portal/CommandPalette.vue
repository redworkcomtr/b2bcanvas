<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

import Dialog from '@/components/ui/Dialog.vue';

const props = defineProps<{
    open: boolean;
    items: Array<{ label: string; caption?: string; to: string; group: string }>;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    select: [to: string];
}>();

const query = ref('');
const filtered = computed(() => {
    const needle = query.value.toLowerCase();
    return props.items.filter((item) => `${item.group} ${item.label} ${item.caption ?? ''}`.toLowerCase().includes(needle)).slice(0, 12);
});

watch(() => props.open, (open) => {
    if (open) {
        query.value = '';
    }
});
</script>

<template>
    <Dialog :open="open" title="Global Search" description="Jump to orders, mappings, issues, and operational screens." @update:open="emit('update:open', $event)">
        <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
            <input
                v-model="query"
                autofocus
                class="focus-ring h-11 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm shadow-sm"
                placeholder="Search orders, customers, tickets..."
            >
        </div>
        <div class="mt-4 grid gap-2">
            <button
                v-for="item in filtered"
                :key="item.group + item.to + item.label"
                class="focus-ring rounded-md border border-slate-200 p-3 text-left transition hover:border-teal-300 hover:bg-teal-50/60"
                @click="emit('select', item.to); emit('update:open', false)"
            >
                <div class="flex items-center justify-between gap-3">
                    <p class="font-semibold text-slate-950">{{ item.label }}</p>
                    <span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500">{{ item.group }}</span>
                </div>
                <p v-if="item.caption" class="mt-1 line-clamp-1 text-sm text-slate-500">{{ item.caption }}</p>
            </button>
            <div v-if="filtered.length === 0" class="rounded-md border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                No matching command or record.
            </div>
        </div>
    </Dialog>
</template>
