<script setup lang="ts">
defineProps<{
    minWidth?: string;
}>();
</script>

<template>
    <div class="scrollbar-thin min-w-0 overflow-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full text-left text-sm" :style="{ minWidth: minWidth ?? '900px' }">
            <slot />
        </table>
    </div>
</template>
