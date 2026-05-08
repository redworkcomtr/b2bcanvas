<script setup lang="ts">
import { computed } from 'vue';

import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{
    tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info';
}>(), {
    tone: 'neutral',
});

const classes = computed(() => cn(
    'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold capitalize',
    {
        neutral: 'border-slate-200 bg-slate-100 text-slate-700',
        success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        warning: 'border-amber-200 bg-amber-50 text-amber-800',
        danger: 'border-red-200 bg-red-50 text-red-700',
        info: 'border-sky-200 bg-sky-50 text-sky-700',
    }[props.tone],
));
</script>

<template>
    <span :class="classes">
        <slot />
    </span>
</template>
