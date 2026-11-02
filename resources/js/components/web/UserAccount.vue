<script setup>
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import {
    clearUserToken,
    getUserToken,
    saveUserToken,
    userApiRequest,
} from '../../userApi';
import WebBottomNav from './WebBottomNav.vue';

const authReady = ref(false);
const user = ref(null);
const mode = ref('login');
const loading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const loginForm = reactive({
    email: '',
    password: '',
});

const registerForm = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const profileForm = reactive({ name: '' });

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function handleAuthExpired() {
    user.value = null;
}

function applyUser(userData) {
    user.value = userData;
    profileForm.name = userData.name;
}

async function restoreSession() {
    try {
        if (getUserToken()) {
            const response = await userApiRequest('/api/auth/me');
            applyUser(response.user);
        }
    } catch (error) {
        if (error.status !== 401) {
            errorMessage.value = error.message;
        }
    } finally {
        authReady.value = true;
    }
}

async function login() {
    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await userApiRequest('/api/auth/login', {
            method: 'POST',
            body: JSON.stringify(loginForm),
        });

        saveUserToken(response.access_token);
        applyUser(response.user);
        loginForm.password = '';
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function register() {
    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await userApiRequest('/api/auth/register', {
            method: 'POST',
            body: JSON.stringify(registerForm),
        });

        saveUserToken(response.access_token);
        applyUser(response.user);
        registerForm.password = '';
        registerForm.password_confirmation = '';
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function updateProfile() {
    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await userApiRequest('/api/auth/me', {
            method: 'PATCH',
            body: JSON.stringify({ name: profileForm.name }),
        });

        applyUser(response.user);
        successMessage.value = response.message;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function updatePassword() {
    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await userApiRequest('/api/auth/me/password', {
            method: 'PATCH',
            body: JSON.stringify(passwordForm),
        });

        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        successMessage.value = response.message;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function logout() {
    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await userApiRequest('/api/auth/logout', {
            method: 'POST',
        });
        successMessage.value = response.message;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        clearUserToken();
        user.value = null;
        loading.value = false;
    }
}

onMounted(() => {
    window.addEventListener('user-auth-expired', handleAuthExpired);
    restoreSession();
});

onUnmounted(() => {
    window.removeEventListener('user-auth-expired', handleAuthExpired);
});
</script>

<template>
    <main class="min-h-screen bg-[#f3f5f2] px-4 pt-10 pb-28 text-[#17231f] sm:px-6 md:pb-10">
        <section class="mx-auto max-w-xl">
            <RouterLink
                to="/web"
                class="text-sm font-medium text-emerald-800 hover:underline"
            >
                返回首页
            </RouterLink>

            <div class="mt-5 rounded-xl border border-[#d6ded8] bg-white p-6 shadow-sm sm:p-8">
                <p class="text-sm font-medium text-emerald-800">账户</p>

                <p
                    v-if="errorMessage"
                    role="alert"
                    class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
                >
                    {{ errorMessage }}
                </p>

                <p
                    v-if="successMessage"
                    role="status"
                    class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
                >
                    {{ successMessage }}
                </p>

                <p v-if="!authReady" class="mt-6 text-sm text-slate-600">
                    正在恢复登录状态…
                </p>

                <template v-else-if="user">
                    <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-semibold">个人信息</h1>
                            <p class="mt-2 text-sm text-slate-600">{{ user.email }}</p>
                        </div>

                        <button
                            type="button"
                            class="rounded border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50"
                            :disabled="loading"
                            @click="logout"
                        >
                            {{ loading ? '处理中…' : '登出' }}
                        </button>
                    </div>

                    <form class="mt-6 space-y-3 border-t border-slate-200 pt-6" @submit.prevent="updateProfile">
                        <label class="block">
                            <span class="text-sm font-medium">姓名</span>
                            <input
                                v-model="profileForm.name"
                                type="text"
                                maxlength="255"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <button
                            type="submit"
                            :disabled="loading"
                            class="rounded bg-emerald-800 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-900 disabled:opacity-50"
                        >
                            {{ loading ? '处理中…' : '保存个人信息' }}
                        </button>
                    </form>

                    <form class="mt-6 space-y-3 border-t border-slate-200 pt-6" @submit.prevent="updatePassword">
                        <h2 class="text-lg font-semibold">修改密码</h2>

                        <label class="block">
                            <span class="text-sm font-medium">当前密码</span>
                            <input
                                v-model="passwordForm.current_password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">新密码（至少 8 位）</span>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                                minlength="8"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">确认新密码</span>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                minlength="8"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <button
                            type="submit"
                            :disabled="loading"
                            class="rounded bg-emerald-800 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-900 disabled:opacity-50"
                        >
                            {{ loading ? '处理中…' : '修改密码' }}
                        </button>
                    </form>
                </template>

                <template v-else>
                    <h1 class="mt-6 text-2xl font-semibold">
                        {{ mode === 'login' ? '登录' : '注册' }}
                    </h1>

                    <div class="mt-5 flex border-b border-slate-200">
                        <button
                            type="button"
                            class="border-b-2 px-4 py-2 text-sm"
                            :class="mode === 'login'
                                ? 'border-emerald-800 font-medium text-emerald-900'
                                : 'border-transparent text-slate-600'"
                            @click="mode = 'login'; errorMessage = ''; successMessage = ''"
                        >
                            登录
                        </button>
                        <button
                            type="button"
                            class="border-b-2 px-4 py-2 text-sm"
                            :class="mode === 'register'
                                ? 'border-emerald-800 font-medium text-emerald-900'
                                : 'border-transparent text-slate-600'"
                            @click="mode = 'register'; errorMessage = ''; successMessage = ''"
                        >
                            注册
                        </button>
                    </div>

                    <form v-if="mode === 'login'" class="mt-5 space-y-4" @submit.prevent="login">
                        <label class="block">
                            <span class="text-sm font-medium">邮箱</span>
                            <input
                                v-model="loginForm.email"
                                type="email"
                                autocomplete="username"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">密码</span>
                            <input
                                v-model="loginForm.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full rounded bg-emerald-800 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-900 disabled:opacity-50"
                        >
                            {{ loading ? '处理中…' : '登录' }}
                        </button>
                    </form>

                    <form v-else class="mt-5 space-y-4" @submit.prevent="register">
                        <label class="block">
                            <span class="text-sm font-medium">姓名</span>
                            <input
                                v-model="registerForm.name"
                                type="text"
                                maxlength="255"
                                autocomplete="name"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">邮箱</span>
                            <input
                                v-model="registerForm.email"
                                type="email"
                                autocomplete="email"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">密码（至少 8 位）</span>
                            <input
                                v-model="registerForm.password"
                                type="password"
                                autocomplete="new-password"
                                minlength="8"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium">确认密码</span>
                            <input
                                v-model="registerForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                minlength="8"
                                required
                                class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                            >
                        </label>

                        <button
                            type="submit"
                            :disabled="loading"
                            class="w-full rounded bg-emerald-800 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-900 disabled:opacity-50"
                        >
                            {{ loading ? '处理中…' : '创建账户' }}
                        </button>
                    </form>
                </template>
            </div>
        </section>

        <WebBottomNav />
    </main>
</template>
