<script setup lang="ts">
import { X } from 'lucide-vue-next';

import Button from './Button.vue';

defineProps<{
    open: boolean;
    title: string;
    description?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/40 p-4 backdrop-blur-sm" @click.self="emit('update:open', false)">
            <section class="max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-950">{{ title }}</h2>
                        <p v-if="description" class="mt-1 text-sm text-slate-500">{{ description }}</p>
                    </div>
                    <Button variant="ghost" size="icon" aria-label="Close dialog" @click="emit('update:open', false)">
                        <X class="h-4 w-4" />
                    </Button>
                </header>
                <div class="scrollbar-thin max-h-[calc(90vh-82px)] overflow-auto p-5">
                    <slot />
                </div>
            </section>
        </div>
    </Teleport>
</template>
