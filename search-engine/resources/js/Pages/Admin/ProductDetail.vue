<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    product: { type: Object, required: true },
});
</script>

<template>
    <div>
        <Link href="/admin/products" class="mb-4 inline-block text-sm text-blue-700 hover:underline">
            &larr; Back to Products
        </Link>

        <div class="mb-6 flex items-center gap-4">
            <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.name"
                class="h-16 w-16 rounded object-cover"
            />
            <div>
                <h1 class="text-xl font-semibold text-gray-900">{{ product.name }}</h1>
                <p class="text-sm text-gray-500">
                    {{ product.category || '—' }} · {{ product.brand || '—' }}
                </p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Domain</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Availability</th>
                        <th class="px-4 py-3">Extracted At</th>
                        <th class="px-4 py-3">Link</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="price in product.prices"
                        :key="price.id"
                        class="border-b border-gray-100 last:border-0"
                    >
                        <td class="px-4 py-3 font-medium text-gray-900">{{ price.domain?.name }}</td>
                        <td class="px-4 py-3">{{ price.price_formatted }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-green-100 text-green-700': price.availability === 'in_stock',
                                    'bg-red-100 text-red-700': price.availability === 'out_of_stock',
                                    'bg-gray-100 text-gray-600': price.availability === 'unknown',
                                }"
                            >
                                {{ price.availability }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ price.extracted_at ? new Date(price.extracted_at).toLocaleString() : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <a
                                :href="price.product_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-blue-700 hover:underline"
                            >
                                View
                            </a>
                        </td>
                    </tr>
                    <tr v-if="product.prices.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">No prices recorded.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
