<script setup lang="ts">
import { cva } from 'class-variance-authority';
import { computed, useAttrs } from 'vue';

import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = withDefaults(defineProps<{
    variant?: 'default' | 'secondary' | 'outline' | 'ghost' | 'destructive';
    size?: 'sm' | 'md' | 'lg' | 'icon';
}>(), {
    variant: 'default',
    size: 'md',
});

const attrs = useAttrs();
const buttonVariants = cva(
    'focus-ring relative inline-flex shrink-0 items-center justify-center gap-2 rounded-md border text-sm font-semibold transition disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                default: 'border-transparent bg-[hsl(var(--primary))] text-[hsl(var(--primary-foreground))] shadow-sm hover:bg-[hsl(var(--primary-dark))]',
                secondary: 'border-transparent bg-slate-900 text-white hover:bg-slate-800',
                outline: 'border-[hsl(var(--border))] bg-white text-slate-800 hover:bg-slate-50',
                ghost: 'border-transparent bg-transparent text-slate-700 hover:bg-slate-100',
                destructive: 'border-transparent bg-red-600 text-white hover:bg-red-700',
            },
            size: {
                sm: 'h-8 px-3',
                md: 'h-10 px-4',
                lg: 'h-11 px-5',
                icon: 'h-10 w-10',
            },
        },
    },
);

const classes = computed(() => cn(buttonVariants({ variant: props.variant, size: props.size }), attrs.class as string));
</script>

<template>
    <button v-bind="attrs" :class="classes">
        <slot />
    </button>
</template>
