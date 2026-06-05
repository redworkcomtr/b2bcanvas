<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    label?: string;
    sortKey?: string;
    sort?: string;
    direction?: 'asc' | 'desc' | string;
    align?: 'left' | 'center' | 'right';
    sticky?: boolean;
}>(), {
    align: 'left',
});

const emit = defineEmits<{
    sort: [key: string];
}>();

const sortable = computed(() => Boolean(props.sortKey));
const active = computed(() => Boolean(props.sortKey && props.sort === props.sortKey));
const icon = computed(() => {
    if (!active.value) {
        return ArrowUpDown;
    }

    return props.direction === 'asc' ? ArrowUp : ArrowDown;
});
const alignmentClass = computed(() => ({
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
}[props.align]));
const buttonAlignmentClass = computed(() => ({
    left: 'justify-start',
    center: 'justify-center',
    right: 'justify-end',
}[props.align]));

function toggleSort() {
    if (props.sortKey) {
        emit('sort', props.sortKey);
    }
}
</script>

<template>
    <th :class="['px-4 py-3', alignmentClass, sticky ? 'data-table__sticky-cell' : '']">
        <button
            v-if="sortable"
            type="button"
            :class="[
                'group inline-flex w-full items-center gap-1.5 font-sans text-[11px] font-semibold uppercase leading-none text-zinc-500 transition-colors hover:text-zinc-950',
                buttonAlignmentClass,
                active ? 'text-zinc-950' : '',
            ]"
            @click="toggleSort"
        >
            <span class="truncate">
                <slot>{{ label }}</slot>
            </span>
            <component :is="icon" :class="['h-3.5 w-3.5 shrink-0', active ? 'text-zinc-950' : 'text-zinc-400 group-hover:text-zinc-700']" />
        </button>
        <span v-else class="font-sans text-[11px] font-semibold uppercase leading-none text-zinc-500">
            <slot>{{ label }}</slot>
        </span>
    </th>
</template>
