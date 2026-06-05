<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useAttrs } from 'vue';

import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = withDefaults(defineProps<{
    minWidth?: string;
    stickyActions?: boolean;
    density?: 'default' | 'compact';
}>(), {
    density: 'default',
});

const attrs = useAttrs();
const wrapper = ref<HTMLElement | null>(null);
const actionsElevated = ref(false);
let resizeObserver: ResizeObserver | null = null;

const wrapperClasses = computed(() => cn(
    'sidebar-scroll data-table min-w-0 overflow-auto rounded-lg border border-zinc-200/80 bg-white',
    props.stickyActions ? 'data-table--sticky-actions' : '',
    actionsElevated.value ? 'data-table--actions-elevated' : '',
    props.density === 'compact' ? 'data-table--compact' : '',
    attrs.class as string,
));

function updateActionElevation() {
    if (!props.stickyActions || !wrapper.value) {
        actionsElevated.value = false;
        return;
    }

    const element = wrapper.value;
    const maxScroll = element.scrollWidth - element.clientWidth;
    actionsElevated.value = maxScroll > 2 && element.scrollLeft < maxScroll - 2;
}

onMounted(() => {
    void nextTick(updateActionElevation);

    if (typeof ResizeObserver !== 'undefined' && wrapper.value) {
        resizeObserver = new ResizeObserver(updateActionElevation);
        resizeObserver.observe(wrapper.value);
    }
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
});
</script>

<template>
    <div ref="wrapper" v-bind="attrs" :class="wrapperClasses" @scroll="updateActionElevation">
        <table class="w-full border-separate border-spacing-0 text-left text-sm" :style="{ minWidth: minWidth ?? '900px' }">
            <slot />
        </table>
    </div>
</template>
