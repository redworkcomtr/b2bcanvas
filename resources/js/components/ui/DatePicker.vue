<script setup lang="ts">
defineProps<{
    label?: string;
    modelValue?: string;
    required?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <label class="grid min-w-0 gap-1.5 text-[14px] font-medium text-zinc-700">
        <span v-if="label" class="text-[12px] font-semibold text-zinc-600">{{ label }} <span v-if="required" class="text-red-600">*</span></span>
        <input
            type="date"
            class="focus-ring h-10 min-w-0 rounded-md border border-zinc-300/80 bg-white px-3 text-[14px] font-medium text-zinc-950 shadow-[inset_0_1px_1px_rgba(15,23,42,0.03)]"
            :value="modelValue"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        >
    </label>
</template>
