<script setup lang="ts">
import { computed } from 'vue';

import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{
    tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info';
}>(), {
    tone: 'neutral',
});

const classes = computed(() => cn(
    'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-semibold capitalize',
    {
        neutral: 'border-zinc-200 bg-white text-zinc-600',
        success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        warning: 'border-amber-200 bg-amber-50 text-amber-700',
        danger: 'border-red-200 bg-red-50 text-red-700',
        info: 'border-blue-200 bg-blue-50 text-blue-700',
    }[props.tone],
));
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
