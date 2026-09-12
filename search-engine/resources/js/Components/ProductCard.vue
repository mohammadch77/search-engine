<script setup>
import { ref } from 'vue';
import { dirFor } from '@/utils/rtl';
import PriceComparison from '@/Components/PriceComparison.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const expanded = ref(false);

function toggle() {
    expanded.value = !expanded.value;
}
</script>

<template>
    <article class="max-w-2xl rounded-lg border border-gray-100 p-4" :dir="dirFor(product.name)">
        <div class="flex items-start gap-3">
            <img
                v-if="product.image_url"
                :src="product.image_url"
                :alt="product.name"
                class="h-16 w-16 shrink-0 rounded object-cover"
            />
            <div class="min-w-0 flex-1">
                <h3 class="truncate text-base font-medium text-gray-900">{{ product.name }}</h3>
                <p class="mt-1 text-2xl font-bold text-blue-700">{{ product.lowest_price_formatted }}</p>
                <button
                    type="button"
                    class="mt-2 rounded bg-blue-50 px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-100"
                    @click="toggle"
                >
                    {{ expanded ? 'بستن' : `مقایسه قیمت در ${product.domains_count} فروشگاه` }}
                </button>
            </div>
        </div>

        <div v-if="expanded" class="mt-4">
            <PriceComparison :prices="product.prices" />
        </div>
    </article>
</template>
