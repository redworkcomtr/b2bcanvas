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
    <div class="grid place-items-center rounded-lg border border-dashed border-zinc-300 bg-zinc-50 px-6 py-10 text-center">
        <div class="grid h-10 w-10 place-items-center rounded-md border border-zinc-200 bg-white text-zinc-500">
            <component :is="icon" class="h-5 w-5" />
        </div>
        <h3 class="mt-3 text-[14px] font-semibold text-zinc-950">{{ title }}</h3>
        <p v-if="description" class="mt-1 max-w-md text-[14px] leading-6 text-zinc-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
