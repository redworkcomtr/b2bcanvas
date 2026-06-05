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
    'focus-ring relative inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-md border border-transparent font-sans text-sm font-medium leading-none transition-colors disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:h-4 [&_svg]:w-4 [&_svg]:shrink-0',
    {
        variants: {
            variant: {
                default: 'bg-zinc-950 text-zinc-50 shadow hover:bg-zinc-950/90',
                secondary: 'bg-zinc-100 text-zinc-900 shadow-sm hover:bg-zinc-100/80',
                outline: 'border-zinc-200 bg-white text-zinc-950 shadow-sm hover:bg-zinc-100 hover:text-zinc-950',
                ghost: 'bg-transparent text-zinc-950 hover:bg-zinc-100 hover:text-zinc-950',
                destructive: 'bg-red-600 text-white shadow-sm hover:bg-red-600/90',
            },
            size: {
                sm: 'h-8 rounded-md px-3 text-xs',
                md: 'h-9 px-4 py-2',
                lg: 'h-10 rounded-md px-8',
                icon: 'h-9 w-9',
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
