<script setup>
import { computed } from 'vue';
import { dirFor } from '@/utils/rtl';

const props = defineProps({
    prices: {
        type: Array,
        required: true,
    },
});

const sortedPrices = computed(() => [...props.prices].sort((a, b) => a.price - b.price));

const availabilityLabel = (availability) => {
    if (availability === 'in_stock') return 'موجود';
    if (availability === 'out_of_stock') return 'ناموجود';
    return 'نامشخص';
};

const availabilityClass = (availability) => {
    if (availability === 'in_stock') return 'bg-green-100 text-green-700';
    if (availability === 'out_of_stock') return 'bg-red-100 text-red-700';
    return 'bg-gray-100 text-gray-600';
};
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-right text-sm" dir="rtl">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-2">فروشگاه</th>
                    <th class="px-4 py-2">قیمت</th>
                    <th class="px-4 py-2">موجودی</th>
                    <th class="px-4 py-2">خرید</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, i) in sortedPrices"
                    :key="row.product_url + i"
                    class="border-b border-gray-100 last:border-0"
                >
                    <td class="px-4 py-2 font-medium text-gray-800" dir="ltr">{{ row.domain }}</td>
                    <td class="px-4 py-2 text-gray-900">{{ row.price_formatted }}</td>
                    <td class="px-4 py-2">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="availabilityClass(row.availability)">
                            {{ availabilityLabel(row.availability) }}
                        </span>
                    </td>
                    <td class="px-4 py-2">
                        <a
                            :href="row.product_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-blue-700 hover:underline"
                        >
                            خرید
                        </a>
                    </td>
                </tr>
                <tr v-if="sortedPrices.length === 0">
                    <td colspan="4" class="px-4 py-6 text-center text-gray-400">قیمتی یافت نشد.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
