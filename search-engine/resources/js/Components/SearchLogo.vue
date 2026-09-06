<script setup>
defineProps({
    size: {
        type: String,
        default: 'large', // 'large' | 'small'
    },
});
</script>

<template>
    <div
        class="select-none font-bold tracking-tight"
        :class="size === 'large' ? 'text-6xl sm:text-7xl' : 'text-2xl'"
    >
        <span class="text-blue-500">جست</span><span class="text-red-500">و</span><span
            class="text-yellow-500"
        >جو</span><span class="text-blue-500">ی</span><span class="text-green-500">اب</span>
    </div>
</template>
