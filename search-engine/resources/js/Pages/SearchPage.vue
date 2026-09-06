<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import SearchBox from '@/Components/SearchBox.vue';
import SearchLogo from '@/Components/SearchLogo.vue';

const query = ref('');

function search(value) {
    const term = (value ?? query.value).trim();
    if (term === '') return;
    router.get('/search', { q: term });
}
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-white px-4">
        <div class="flex w-full max-w-xl flex-1 flex-col items-center justify-center gap-8 pb-24">
            <SearchLogo size="large" />

            <div class="w-full">
                <SearchBox v-model="query" autofocus size="large" @search="search" />
            </div>

            <div class="flex gap-3">
                <button
                    type="button"
                    class="rounded-md bg-gray-50 px-4 py-2 text-sm text-gray-700 hover:border hover:border-gray-200 hover:shadow-sm"
                    @click="search()"
                >
                    جستجو
                </button>
            </div>
        </div>

        <footer class="w-full border-t border-gray-100 py-4 text-center text-xs text-gray-400">
            موتور جستجوی فارسی
        </footer>
    </div>
</template>
