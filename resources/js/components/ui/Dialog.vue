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
        <div v-if="open" class="fixed inset-0 z-50 grid place-items-center bg-black/20 p-4 backdrop-blur-sm" @click.self="emit('update:open', false)">
            <section class="max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-[0_18px_50px_-24px_rgba(15,23,42,0.45)]">
                <header class="flex items-start justify-between gap-4 border-b border-zinc-200/80 px-6 py-5">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-950">{{ title }}</h2>
                        <p v-if="description" class="mt-1 text-[14px] leading-6 text-zinc-500">{{ description }}</p>
                    </div>
                    <Button variant="ghost" size="icon" aria-label="Close dialog" @click="emit('update:open', false)">
                        <X class="h-4 w-4" />
                    </Button>
                </header>
                <div class="sidebar-scroll max-h-[calc(90vh-82px)] overflow-auto px-6 py-6">
                    <slot />
                </div>
            </section>
        </div>
    </Teleport>
</template>
