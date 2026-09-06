<script setup>
import { router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import SearchBox from '@/Components/SearchBox.vue';
import SearchLogo from '@/Components/SearchLogo.vue';
import Filters from '@/Components/Filters.vue';
import ResultItem from '@/Components/ResultItem.vue';
import Pagination from '@/Components/Pagination.vue';
import { dirFor } from '@/utils/rtl';

const query = ref('');
const domain = ref('');
const lang = ref('');
const sort = ref('relevance');
const page = ref(1);

const results = ref([]);
const total = ref(0);
const lastPage = ref(1);
const timeTakenMs = ref(0);
const loading = ref(false);
const searched = ref(false);

function readFromUrl() {
    const params = new URLSearchParams(window.location.search);
    query.value = params.get('q') || '';
    domain.value = params.get('domain') || '';
    lang.value = params.get('lang') || '';
    sort.value = params.get('sort') || 'relevance';
    page.value = parseInt(params.get('page') || '1', 10) || 1;
}

function syncUrl(replace = false) {
    const params = new URLSearchParams();
    params.set('q', query.value);
    if (domain.value) params.set('domain', domain.value);
    if (lang.value) params.set('lang', lang.value);
    if (sort.value && sort.value !== 'relevance') params.set('sort', sort.value);
    if (page.value > 1) params.set('page', String(page.value));

    const url = `${window.location.pathname}?${params.toString()}`;
    if (replace) {
        window.history.replaceState({}, '', url);
    } else {
        window.history.pushState({}, '', url);
    }
}

async function runSearch({ resetPage = false, replace = false } = {}) {
    const term = query.value.trim();
    if (term === '') {
        results.value = [];
        searched.value = false;
        return;
    }

    if (resetPage) {
        page.value = 1;
    }

    syncUrl(replace);
    loading.value = true;

    try {
        const { data } = await window.axios.get('/api/search', {
            params: {
                q: term,
                page: page.value,
                domain: domain.value || undefined,
                lang: lang.value || undefined,
                sort: sort.value,
            },
        });

        results.value = data.results || [];
        total.value = data.total || 0;
        lastPage.value = data.last_page || 1;
        timeTakenMs.value = data.time_taken_ms || 0;
        searched.value = true;
    } catch (e) {
        results.value = [];
        searched.value = true;
    } finally {
        loading.value = false;
    }
}

function onSearchSubmit(term) {
    query.value = term;
    runSearch({ resetPage: true });
}

function onFilterChange() {
    runSearch({ resetPage: true });
}

function onPageChange(p) {
    page.value = p;
    runSearch();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goHome() {
    router.get('/');
}

onMounted(() => {
    readFromUrl();
    runSearch({ replace: true });

    window.addEventListener('popstate', () => {
        readFromUrl();
        runSearch({ replace: true });
    });
});
</script>

<template>
    <div class="min-h-screen bg-white">
        <header class="sticky top-0 z-10 border-b border-gray-100 bg-white">
            <div class="mx-auto flex max-w-4xl flex-wrap items-center gap-4 px-4 py-3 sm:gap-6">
                <button type="button" class="shrink-0" @click="goHome">
                    <SearchLogo size="small" />
                </button>
                <div class="min-w-[220px] max-w-xl flex-1">
                    <SearchBox v-model="query" size="small" @search="onSearchSubmit" />
                </div>
            </div>
            <div class="mx-auto max-w-4xl px-4 pb-3">
                <Filters
                    v-model:domain="domain"
                    v-model:lang="lang"
                    v-model:sort="sort"
                    @update:domain="onFilterChange"
                    @update:lang="onFilterChange"
                    @update:sort="onFilterChange"
                />
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-4">
            <p v-if="searched && !loading" class="mb-2 text-xs text-gray-500" :dir="dirFor(query)">
                حدود {{ total.toLocaleString('fa-IR') }} نتیجه ({{ (timeTakenMs / 1000).toFixed(2) }} ثانیه)
            </p>

            <div v-if="loading" class="py-10 text-center text-sm text-gray-400">در حال جستجو…</div>

            <template v-else-if="searched">
                <div v-if="results.length === 0" class="py-16 text-center">
                    <p class="text-gray-600">هیچ نتیجه‌ای برای این جستجو پیدا نشد.</p>
                </div>

                <div v-else class="divide-y divide-gray-50">
                    <ResultItem v-for="(r, i) in results" :key="r.url + i" :result="r" />
                </div>

                <Pagination :page="page" :last-page="lastPage" @change="onPageChange" />
            </template>
        </main>
    </div>
</template>
