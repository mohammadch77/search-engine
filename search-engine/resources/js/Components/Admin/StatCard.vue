<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <p class="text-xs uppercase tracking-wide text-gray-500">{{ label }}</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ value }}</p>
    </div>
</template>
