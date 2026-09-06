<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

function submit() {
    form.post('/admin/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-50">
        <form class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm" @submit.prevent="submit">
            <h1 class="mb-6 text-center text-xl font-semibold text-gray-900">Admin Login</h1>

            <label class="mb-1 block text-sm text-gray-600" for="password">Password</label>
            <input
                id="password"
                v-model="form.password"
                type="password"
                autofocus
                class="mb-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
            />
            <p v-if="form.errors.password" class="mb-2 text-xs text-red-600">{{ form.errors.password }}</p>

            <button
                type="submit"
                class="mt-4 w-full rounded bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                :disabled="form.processing"
            >
                Sign in
            </button>
        </form>
    </div>
</template>
