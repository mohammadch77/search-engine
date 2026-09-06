<script setup>
import { computed } from 'vue';

const props = defineProps({
    page: {
        type: Number,
        required: true,
    },
    lastPage: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['change']);

const pages = computed(() => {
    const total = props.lastPage;
    const current = props.page;
    const windowSize = 2;
    const start = Math.max(1, current - windowSize);
    const end = Math.min(total, current + windowSize);
    const list = [];
    for (let p = start; p <= end; p++) list.push(p);
    return list;
});

function go(p) {
    if (p < 1 || p > props.lastPage || p === props.page) return;
    emit('change', p);
}
</script>

<template>
    <nav v-if="lastPage > 1" class="flex items-center justify-center gap-1 py-8" aria-label="Pagination">
        <button
            type="button"
            class="rounded-full px-3 py-2 text-sm text-gray-500 disabled:opacity-40 enabled:hover:bg-gray-100"
            :disabled="page === 1"
            @click="go(page - 1)"
        >
            ‹ قبلی
        </button>

        <button
            v-if="pages[0] > 1"
            type="button"
            class="h-9 w-9 rounded-full text-sm text-gray-600 hover:bg-gray-100"
            @click="go(1)"
        >
            1
        </button>
        <span v-if="pages[0] > 2" class="px-1 text-gray-400">…</span>

        <button
            v-for="p in pages"
            :key="p"
            type="button"
            class="h-9 w-9 rounded-full text-sm"
            :class="p === page ? 'bg-blue-600 font-semibold text-white' : 'text-gray-600 hover:bg-gray-100'"
            @click="go(p)"
        >
            {{ p }}
        </button>

        <span v-if="pages[pages.length - 1] < lastPage - 1" class="px-1 text-gray-400">…</span>
        <button
            v-if="pages[pages.length - 1] < lastPage"
            type="button"
            class="h-9 w-9 rounded-full text-sm text-gray-600 hover:bg-gray-100"
            @click="go(lastPage)"
        >
            {{ lastPage }}
        </button>

        <button
            type="button"
            class="rounded-full px-3 py-2 text-sm text-gray-500 disabled:opacity-40 enabled:hover:bg-gray-100"
            :disabled="page === lastPage"
            @click="go(page + 1)"
        >
            بعدی ›
        </button>
    </nav>
</template>
