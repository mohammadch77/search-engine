<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({
    products: { type: Object, required: true },
});
</script>

<template>
    <div>
        <h1 class="mb-6 text-xl font-semibold text-gray-900">Products</h1>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Brand</th>
                        <th class="px-4 py-3">Prices</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in products.data"
                        :key="product.id"
                        class="cursor-pointer border-b border-gray-100 last:border-0 hover:bg-gray-50"
                    >
                        <td class="px-4 py-3">
                            <Link :href="`/admin/products/${product.id}`" class="font-medium text-blue-700 hover:underline">
                                {{ product.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ product.category || '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ product.brand || '—' }}</td>
                        <td class="px-4 py-3">{{ product.prices_count }}</td>
                    </tr>
                    <tr v-if="products.data.length === 0">
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400">No products yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="products.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="(link, i) in products.links"
                :key="i"
                :href="link.url || '#'"
                class="rounded px-3 py-1 text-xs"
                :class="[
                    link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100',
                    !link.url && 'pointer-events-none opacity-40',
                ]"
                v-html="link.label"
            />
        </div>
    </div>
</template>
