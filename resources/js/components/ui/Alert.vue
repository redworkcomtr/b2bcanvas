<script setup lang="ts">
import { computed, useSlots } from 'vue';

const props = withDefaults(defineProps<{
    tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info';
    title?: string;
    description?: string;
}>(), {
    tone: 'neutral',
});
const slots = useSlots();

const classes = computed(() => ({
    neutral: 'border-zinc-200 bg-zinc-50 text-zinc-700',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    danger: 'border-red-200 bg-red-50 text-red-800',
    info: 'border-blue-200 bg-blue-50 text-blue-800',
}[props.tone]));
</script>

<template>
    <div :class="['rounded-lg border p-4 text-[14px]', classes]">
        <p v-if="title" class="font-semibold">{{ title }}</p>
        <p v-if="description" :class="['leading-6', title ? 'mt-1' : '']">{{ description }}</p>
        <div v-if="slots.default" :class="title || description ? 'mt-1' : ''">
            <slot />
        </div>
    </div>
</template>
