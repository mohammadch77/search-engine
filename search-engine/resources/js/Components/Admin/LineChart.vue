<script setup>
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler);

const props = defineProps({
    labels: { type: Array, required: true },
    values: { type: Array, required: true },
    label: { type: String, default: '' },
    color: { type: String, default: '#2563eb' },
});

const chartData = () => ({
    labels: props.labels,
    datasets: [
        {
            label: props.label,
            data: props.values,
            borderColor: props.color,
            backgroundColor: props.color + '22',
            fill: true,
            tension: 0.3,
            pointRadius: 2,
        },
    ],
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
};
</script>

<template>
    <div class="h-64">
        <Line :data="chartData()" :options="chartOptions" />
    </div>
</template>
