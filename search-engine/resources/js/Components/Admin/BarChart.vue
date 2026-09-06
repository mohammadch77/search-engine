<script setup>
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

const props = defineProps({
    labels: { type: Array, required: true },
    values: { type: Array, required: true },
    label: { type: String, default: '' },
    color: { type: String, default: '#7c3aed' },
});

const chartData = () => ({
    labels: props.labels,
    datasets: [
        {
            label: props.label,
            data: props.values,
            backgroundColor: props.color,
            borderRadius: 4,
        },
    ],
});

const chartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        x: { beginAtZero: true, ticks: { precision: 0 } },
    },
};
</script>

<template>
    <div class="h-64">
        <Bar :data="chartData()" :options="chartOptions" />
    </div>
</template>
