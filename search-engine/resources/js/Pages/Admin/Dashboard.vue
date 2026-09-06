<script setup>
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import LineChart from '@/Components/Admin/LineChart.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    stats: { type: Object, required: true },
    pagesPerDay: { type: Array, required: true },
    searchesPerDay: { type: Array, required: true },
});

const pagesLabels = computed(() => props.pagesPerDay.map((r) => r.date));
const pagesValues = computed(() => props.pagesPerDay.map((r) => r.count));
const searchesLabels = computed(() => props.searchesPerDay.map((r) => r.date));
const searchesValues = computed(() => props.searchesPerDay.map((r) => r.count));
</script>

<template>
    <div>
        <h1 class="mb-6 text-xl font-semibold text-gray-900">Dashboard</h1>

        <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <StatCard label="Total Pages" :value="stats.total_pages.toLocaleString()" />
            <StatCard label="Total Domains" :value="stats.total_domains.toLocaleString()" />
            <StatCard label="Queue Pending" :value="stats.queue.pending.toLocaleString()" />
            <StatCard label="Queue Processing" :value="stats.queue.processing.toLocaleString()" />
            <StatCard label="Queue Failed" :value="stats.queue.failed.toLocaleString()" />
            <StatCard label="Searches Today" :value="stats.searches_today.toLocaleString()" />
        </div>

        <div class="mb-8">
            <StatCard label="Crawl Speed (pages / last hour)" :value="stats.crawl_speed_per_hour.toLocaleString()" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-medium text-gray-700">Pages Crawled (last 30 days)</h2>
                <LineChart :labels="pagesLabels" :values="pagesValues" label="Pages" color="#2563eb" />
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-medium text-gray-700">Searches (last 30 days)</h2>
                <LineChart :labels="searchesLabels" :values="searchesValues" label="Searches" color="#16a34a" />
            </div>
        </div>
    </div>
</template>
