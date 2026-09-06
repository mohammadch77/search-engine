<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    domain: { type: String, default: '' },
    lang: { type: String, default: '' },
    sort: { type: String, default: 'relevance' },
});

const emit = defineEmits(['update:domain', 'update:lang', 'update:sort']);

const domains = ref([]);

onMounted(async () => {
    try {
        const { data } = await window.axios.get('/api/domains');
        domains.value = data.domains || [];
    } catch (e) {
        domains.value = [];
    }
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <select
            :value="domain"
            class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-gray-600 outline-none focus:border-blue-400"
            @change="emit('update:domain', $event.target.value)"
        >
            <option value="">همه دامنه‌ها</option>
            <option v-for="d in domains" :key="d" :value="d">{{ d }}</option>
        </select>

        <select
            :value="lang"
            class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-gray-600 outline-none focus:border-blue-400"
            @change="emit('update:lang', $event.target.value)"
        >
            <option value="">همه زبان‌ها</option>
            <option value="fa">فارسی</option>
            <option value="en">English</option>
        </select>

        <select
            :value="sort"
            class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-gray-600 outline-none focus:border-blue-400"
            @change="emit('update:sort', $event.target.value)"
        >
            <option value="relevance">مرتب‌سازی: مرتبط‌ترین</option>
            <option value="date">مرتب‌سازی: جدیدترین</option>
        </select>
    </div>
</template>
