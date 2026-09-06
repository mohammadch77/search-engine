<script setup>
import { Link, router } from '@inertiajs/vue3';

const nav = [
    { label: 'Dashboard', href: '/admin' },
    { label: 'Domains', href: '/admin/domains' },
    { label: 'Searches', href: '/admin/searches' },
];

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-900" dir="ltr">
        <div class="flex">
            <aside class="flex h-screen w-56 shrink-0 flex-col justify-between border-r border-gray-200 bg-white">
                <div>
                    <div class="px-4 py-5 text-lg font-semibold">Admin Panel</div>
                    <nav class="flex flex-col gap-1 px-2">
                        <Link
                            v-for="item in nav"
                            :key="item.href"
                            :href="item.href"
                            class="rounded px-3 py-2 text-sm text-gray-700 hover:bg-gray-100"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>
                <div class="p-2">
                    <button
                        type="button"
                        class="w-full rounded px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-100"
                        @click="logout"
                    >
                        Log out
                    </button>
                </div>
            </aside>

            <main class="min-h-screen flex-1 overflow-y-auto p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
