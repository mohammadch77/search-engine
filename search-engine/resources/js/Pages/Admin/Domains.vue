<script setup>
import { useForm, router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    domains: { type: Object, required: true },
});

const addForm = useForm({ url: '' });

function addDomain() {
    addForm.post('/admin/domains', {
        preserveScroll: true,
        onSuccess: () => addForm.reset('url'),
    });
}

function setStatus(domain, status) {
    router.patch(`/admin/domains/${domain.id}`, { status }, { preserveScroll: true });
}

function updateSettings(domain) {
    router.patch(
        `/admin/domains/${domain.id}`,
        { max_depth: domain.max_depth, crawl_delay_ms: domain.crawl_delay_ms },
        { preserveScroll: true }
    );
}

function recrawl(domain) {
    router.post(`/admin/domains/${domain.id}/recrawl`, {}, { preserveScroll: true });
}

function destroy(domain) {
    if (! confirm(`Delete domain "${domain.name}" and all its pages? This cannot be undone.`)) {
        return;
    }
    router.delete(`/admin/domains/${domain.id}`, { preserveScroll: true });
}
</script>

<template>
    <div>
        <h1 class="mb-6 text-xl font-semibold text-gray-900">Domains</h1>

        <form class="mb-6 flex gap-2" @submit.prevent="addDomain">
            <input
                v-model="addForm.url"
                type="url"
                required
                placeholder="https://example.com"
                class="w-full max-w-md rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            />
            <button
                type="submit"
                class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                :disabled="addForm.processing"
            >
                Add Domain
            </button>
        </form>
        <p v-if="addForm.errors.url" class="mb-4 -mt-4 text-xs text-red-600">{{ addForm.errors.url }}</p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Domain</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Pages</th>
                        <th class="px-4 py-3">Queue (pending/failed)</th>
                        <th class="px-4 py-3">Depth</th>
                        <th class="px-4 py-3">Delay (ms)</th>
                        <th class="px-4 py-3">Last Crawl</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="domain in domains.data" :key="domain.id" class="border-b border-gray-100 last:border-0">
                        <td class="px-4 py-3 font-medium text-gray-900">{{ domain.name }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="{
                                    'bg-green-100 text-green-700': domain.status === 'active',
                                    'bg-yellow-100 text-yellow-700': domain.status === 'paused',
                                    'bg-gray-100 text-gray-600': domain.status === 'pending',
                                    'bg-red-100 text-red-700': domain.status === 'blocked',
                                }"
                            >
                                {{ domain.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ domain.pages_count.toLocaleString() }}</td>
                        <td class="px-4 py-3">{{ domain.pending_count }} / {{ domain.failed_count }}</td>
                        <td class="px-4 py-3">
                            <input
                                v-model.number="domain.max_depth"
                                type="number"
                                min="1"
                                max="50"
                                class="w-16 rounded border border-gray-300 px-2 py-1 text-xs"
                                @change="updateSettings(domain)"
                            />
                        </td>
                        <td class="px-4 py-3">
                            <input
                                v-model.number="domain.crawl_delay_ms"
                                type="number"
                                min="0"
                                step="100"
                                class="w-20 rounded border border-gray-300 px-2 py-1 text-xs"
                                @change="updateSettings(domain)"
                            />
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ domain.last_crawled_at ? new Date(domain.last_crawled_at).toLocaleString() : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <button
                                    v-if="domain.status !== 'active'"
                                    class="rounded bg-green-50 px-2 py-1 text-xs text-green-700 hover:bg-green-100"
                                    @click="setStatus(domain, 'active')"
                                >
                                    Resume
                                </button>
                                <button
                                    v-if="domain.status === 'active'"
                                    class="rounded bg-yellow-50 px-2 py-1 text-xs text-yellow-700 hover:bg-yellow-100"
                                    @click="setStatus(domain, 'paused')"
                                >
                                    Pause
                                </button>
                                <button
                                    v-if="domain.status !== 'blocked'"
                                    class="rounded bg-red-50 px-2 py-1 text-xs text-red-700 hover:bg-red-100"
                                    @click="setStatus(domain, 'blocked')"
                                >
                                    Block
                                </button>
                                <button
                                    class="rounded bg-blue-50 px-2 py-1 text-xs text-blue-700 hover:bg-blue-100"
                                    @click="recrawl(domain)"
                                >
                                    Re-crawl
                                </button>
                                <button
                                    class="rounded bg-gray-100 px-2 py-1 text-xs text-gray-700 hover:bg-gray-200"
                                    @click="destroy(domain)"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="domains.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-gray-400">No domains yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="domains.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="(link, i) in domains.links"
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
