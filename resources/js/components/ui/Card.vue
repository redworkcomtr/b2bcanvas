<script setup lang="ts">
import { computed, useAttrs } from 'vue';

import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    compact?: boolean;
}>();

const attrs = useAttrs();
const classes = computed(() => cn(
    'min-w-0 rounded-lg border border-zinc-200/80 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04)]',
    props.compact ? 'p-4' : 'p-5 sm:p-6',
    attrs.class as string,
));
</script>

<template>
    <section v-bind="attrs" :class="classes">
        <slot />
    </section>
</template>
