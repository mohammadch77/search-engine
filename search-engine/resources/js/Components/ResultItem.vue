<script setup>
import { computed } from 'vue';
import { dirFor } from '@/utils/rtl';

const props = defineProps({
    result: {
        type: Object,
        required: true,
    },
});

const displayUrl = computed(() => {
    try {
        const u = new URL(props.result.url);
        return u.origin + u.pathname.replace(/\/$/, '');
    } catch {
        return props.result.url;
    }
});

const crawledLabel = computed(() => {
    if (!props.result.crawled_at) return null;
    const date = new Date(props.result.crawled_at);
    return date.toLocaleDateString('fa-IR-u-nu-latn', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
});
</script>

<template>
    <article class="max-w-2xl py-4" :dir="dirFor(result.title + ' ' + result.snippet)">
        <div class="flex items-center gap-2 text-xs text-gray-500">
            <span class="font-medium text-gray-700">{{ result.domain }}</span>
            <span v-if="crawledLabel">·</span>
            <span v-if="crawledLabel">{{ crawledLabel }}</span>
        </div>
        <a
            :href="result.url"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-1 block text-xl text-blue-700 hover:underline"
        >
            {{ result.title }}
        </a>
        <a
            :href="result.url"
            target="_blank"
            rel="noopener noreferrer"
            class="block truncate text-sm text-green-700 hover:underline"
            dir="ltr"
        >
            {{ displayUrl }}
        </a>
        <p
            v-if="result.snippet"
            class="mt-1 text-sm leading-relaxed text-gray-600 [&_mark]:bg-yellow-200 [&_mark]:text-gray-900 [&_mark]:font-medium"
            v-html="result.snippet"
        />
    </article>
</template>
