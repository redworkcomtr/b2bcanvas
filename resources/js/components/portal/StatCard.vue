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
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-slate-500">{{ label }}</p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ value }}</p>
                <p v-if="caption" class="mt-1 text-sm text-slate-500">{{ caption }}</p>
            </div>
            <div class="rounded-md bg-teal-50 p-2 text-teal-700">
                <component :is="icon" class="h-5 w-5" />
            </div>
        </div>
    </div>
</template>
