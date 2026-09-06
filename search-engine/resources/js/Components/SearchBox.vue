<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { dirFor } from '@/utils/rtl';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'large', // 'large' | 'small'
    },
    autofocus: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'search']);

const query = ref(props.modelValue);
const suggestions = ref([]);
const showSuggestions = ref(false);
const activeIndex = ref(-1);
const inputRef = ref(null);
let debounceTimer = null;
let requestToken = 0;

watch(
    () => props.modelValue,
    (value) => {
        if (value !== query.value) {
            query.value = value;
        }
    }
);

function onInput() {
    emit('update:modelValue', query.value);
    activeIndex.value = -1;

    clearTimeout(debounceTimer);

    const term = query.value.trim();
    if (term === '') {
        suggestions.value = [];
        showSuggestions.value = false;
        return;
    }

    debounceTimer = setTimeout(async () => {
        const token = ++requestToken;
        try {
            const { data } = await window.axios.get('/api/search/suggest', {
                params: { q: term },
            });
            if (token === requestToken) {
                suggestions.value = data.suggestions || [];
                showSuggestions.value = suggestions.value.length > 0;
            }
        } catch (e) {
            if (token === requestToken) {
                suggestions.value = [];
                showSuggestions.value = false;
            }
        }
    }, 300);
}

function submit(term) {
    const value = (term ?? query.value).trim();
    if (value === '') {
        return;
    }
    query.value = value;
    emit('update:modelValue', value);
    showSuggestions.value = false;
    activeIndex.value = -1;
    emit('search', value);
}

function onKeydown(e) {
    if (!showSuggestions.value || suggestions.value.length === 0) {
        if (e.key === 'Enter') {
            submit();
        }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex.value = (activeIndex.value + 1) % suggestions.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex.value =
            activeIndex.value <= 0 ? suggestions.value.length - 1 : activeIndex.value - 1;
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (activeIndex.value >= 0) {
            submit(suggestions.value[activeIndex.value]);
        } else {
            submit();
        }
    } else if (e.key === 'Escape') {
        showSuggestions.value = false;
        activeIndex.value = -1;
    }
}

function selectSuggestion(s) {
    submit(s);
}

function onBlur() {
    // Delay so a click on a suggestion registers before the list closes.
    setTimeout(() => {
        showSuggestions.value = false;
        activeIndex.value = -1;
    }, 150);
}

function onFocus() {
    if (suggestions.value.length > 0) {
        showSuggestions.value = true;
    }
}

onBeforeUnmount(() => clearTimeout(debounceTimer));
</script>

<template>
    <div class="relative w-full" :dir="dirFor(query)">
        <div
            class="flex items-center gap-3 rounded-full border border-gray-200 bg-white px-5 shadow-sm transition-shadow hover:shadow-md focus-within:shadow-md"
            :class="size === 'large' ? 'h-14' : 'h-11'"
        >
            <svg
                class="h-5 w-5 shrink-0 text-gray-400"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"
                />
            </svg>
            <input
                ref="inputRef"
                v-model="query"
                type="text"
                :autofocus="autofocus"
                class="w-full min-w-0 bg-transparent text-base text-gray-800 outline-none placeholder:text-gray-400"
                :placeholder="'جستجو یا Search…'"
                autocomplete="off"
                spellcheck="false"
                @input="onInput"
                @keydown="onKeydown"
                @focus="onFocus"
                @blur="onBlur"
            />
            <button
                v-if="query"
                type="button"
                class="shrink-0 rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                @mousedown.prevent="() => { query = ''; emit('update:modelValue', ''); suggestions = []; showSuggestions = false; inputRef?.focus(); }"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <ul
            v-if="showSuggestions"
            class="absolute z-20 mt-1 w-full overflow-hidden rounded-2xl border border-gray-100 bg-white py-1 shadow-lg"
        >
            <li
                v-for="(s, i) in suggestions"
                :key="s"
                class="cursor-pointer px-5 py-2 text-sm text-gray-700"
                :class="i === activeIndex ? 'bg-gray-100' : 'hover:bg-gray-50'"
                :dir="dirFor(s)"
                @mousedown.prevent="selectSuggestion(s)"
                @mousemove="activeIndex = i"
            >
                {{ s }}
            </li>
        </ul>
    </div>
</template>
