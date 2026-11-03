<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { apiRequest } from '../../api.js';

const route = useRoute();
const router = useRouter();
const email = ref('');
const token = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const errorMessage = ref('');
const successMessage = ref('');
const loading = ref(false);

onMounted(() => {
    email.value = typeof route.query.email === 'string' ? route.query.email : '';
    token.value = typeof route.query.token === 'string' ? route.query.token : '';
    router.replace({ path: '/admin/reset-password' });

    if (!email.value || !token.value) {
        errorMessage.value = '重置链接无效或已过期。';
    }
});

async function resetPassword() {
    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await apiRequest('/api/admin/reset-password', {
            method: 'POST',
            body: JSON.stringify({
                email: email.value,
                token: token.value,
                password: password.value,
                password_confirmation: passwordConfirmation.value,
            }),
        });

        successMessage.value = response.message;
        token.value = '';
        password.value = '';
        passwordConfirmation.value = '';
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-slate-100 p-4 text-slate-900">
        <section class="w-full max-w-md rounded-xl bg-white p-8 shadow">
            <p class="text-sm font-medium text-slate-500">LaravelVueAdmin / 管理后台</p>
            <h1 class="mt-3 text-2xl font-bold">重置管理员密码</h1>
            <p v-if="email" class="mt-2 text-sm text-slate-600">{{ email }}</p>

            <form v-if="token" class="mt-6 space-y-4" @submit.prevent="resetPassword">
                <label class="block">
                    <span class="text-sm font-medium">新密码（至少 8 位）</span>
                    <input
                        v-model="password"
                        type="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    >
                </label>

                <label class="block">
                    <span class="text-sm font-medium">确认新密码</span>
                    <input
                        v-model="passwordConfirmation"
                        type="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    >
                </label>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                >
                    {{ loading ? '处理中…' : '重置密码' }}
                </button>
            </form>

            <p v-if="errorMessage" class="mt-4 text-sm text-red-600" role="alert">
                {{ errorMessage }}
            </p>
            <p v-if="successMessage" class="mt-4 text-sm text-emerald-700" role="status">
                {{ successMessage }}
            </p>

            <RouterLink to="/admin" class="mt-6 inline-block text-sm text-blue-700 hover:underline">
                返回管理员登录
            </RouterLink>
        </section>
    </main>
</template>