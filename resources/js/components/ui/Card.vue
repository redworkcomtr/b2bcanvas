<script setup lang="ts">
defineProps<{
    compact?: boolean;
}>();
</script>

<template>
    <section :class="['rounded-lg border border-slate-200 bg-white shadow-sm', compact ? 'p-4' : 'p-5']">
        <slot />
    </section>
</template>
