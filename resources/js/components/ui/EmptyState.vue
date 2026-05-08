<script setup lang="ts">
import type { Component } from 'vue';
import { Inbox } from 'lucide-vue-next';

withDefaults(defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>(), {
    icon: Inbox,
});
</script>

<template>
    <div class="grid place-items-center rounded-lg border border-dashed border-slate-300 bg-slate-50/60 px-6 py-10 text-center">
        <component :is="icon" class="h-8 w-8 text-slate-400" />
        <h3 class="mt-3 text-sm font-bold text-slate-950">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-md text-sm text-slate-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
