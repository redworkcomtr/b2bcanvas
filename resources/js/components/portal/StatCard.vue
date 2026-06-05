<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: string | number;
    caption?: string;
    icon: Component;
}>();
</script>

<template>
    <div class="min-w-0 rounded-lg border border-zinc-200/80 bg-white p-4 shadow-[0_1px_2px_rgba(15,23,42,0.04)] transition-colors hover:border-zinc-300">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="truncate text-[12px] font-semibold text-zinc-500">{{ label }}</p>
                <p class="mt-2 text-2xl font-semibold leading-none text-zinc-950">{{ value }}</p>
                <p v-if="caption" class="mt-2 line-clamp-2 text-xs leading-5 text-zinc-500">{{ caption }}</p>
            </div>
            <div class="grid h-9 w-9 shrink-0 place-items-center rounded-md border border-zinc-200 bg-zinc-50 text-zinc-700">
                <component :is="icon" class="h-4 w-4 stroke-[1.8]" />
            </div>
        </div>
    </div>
</template>
