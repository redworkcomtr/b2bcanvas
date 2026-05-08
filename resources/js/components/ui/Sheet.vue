<script setup lang="ts">
import { X } from 'lucide-vue-next';

import Button from './Button.vue';

defineProps<{
    open: boolean;
    title?: string;
    side?: 'left' | 'right';
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 bg-slate-950/40 backdrop-blur-sm" @click.self="emit('update:open', false)">
            <aside
                :class="[
                    'scrollbar-thin fixed top-0 h-full w-[min(420px,92vw)] overflow-auto border-slate-200 bg-white shadow-2xl',
                    side === 'right' ? 'right-0 border-l' : 'left-0 border-r',
                ]"
            >
                <header class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
                    <h2 class="font-bold text-slate-950">{{ title }}</h2>
                    <Button variant="ghost" size="icon" aria-label="Close panel" @click="emit('update:open', false)">
                        <X class="h-4 w-4" />
                    </Button>
                </header>
                <div class="p-5">
                    <slot />
                </div>
            </aside>
        </div>
    </Teleport>
</template>
