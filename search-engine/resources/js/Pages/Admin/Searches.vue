<script setup>
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BarChart from '@/Components/Admin/BarChart.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    recent: { type: Array, required: true },
    popular: { type: Array, required: true },
});

const popularLabels = computed(() => props.popular.map((r) => r.query));
const popularValues = computed(() => props.popular.map((r) => r.count));
</script>

<template>
    <div>
        <h1 class="mb-6 text-xl font-semibold text-gray-900">Search Logs</h1>

        <div class="mb-8 rounded-lg border border-gray-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-medium text-gray-700">Most Popular Searches (last 30 days)</h2>
            <BarChart :labels="popularLabels" :values="popularValues" label="Searches" />
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Query</th>
                        <th class="px-4 py-3">Results</th>
                        <th class="px-4 py-3">Response Time</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Searched At</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in recent" :key="i" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-900" dir="auto">{{ row.query }}</td>
                        <td class="px-4 py-3">{{ row.results_count }}</td>
                        <td class="px-4 py-3">{{ row.response_time_ms }} ms</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ row.ip_address || '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ new Date(row.searched_at).toLocaleString() }}</td>
                    </tr>
                    <tr v-if="recent.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">No searches logged yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
