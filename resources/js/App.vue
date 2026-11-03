<script setup>
import { apiRequest } from './api';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import { pageRouteNames } from './router';
import AdminLayout from './components/admin/AdminLayout.vue';
import OwnPasswordForm from './components/admin/OwnPasswordForm.vue';

const adminManagementRef = ref(null);
const authReady = ref(false);

async function editOwnProfile() {
    showOwnPasswordForm.value = false;

    if (route.name !== pageRouteNames.admins) {
        await router.push({ name: pageRouteNames.admins });
    }

    await nextTick();
    await adminManagementRef.value?.editOwnProfile();
}

const email = ref('');
const password = ref('');
const forgotPasswordMode = ref(false);
const admin = ref(null);
const errorMessage = ref('');
const loading = ref(false);
const successMessage = ref('');
const showOwnPasswordForm = ref(false);
let successMessageTimer = null;

const route = useRoute();
const router = useRouter();
const tokenKey = 'admin_access_token';
const tokenRotatedAtKey = 'admin_access_token_rotated_at';

const siteTitles = ref({});

function applySiteTitle() {
    const key = route.meta.withAdmin
        ? 'web_admin_title'
        : 'web_title';
    const title = siteTitles.value[key];

    if (typeof title === 'string' && title.trim() !== '') {
        document.title = title.trim();
    }
}

watch(() => route.meta.withAdmin, applySiteTitle);

watch(
    () => route.meta.withAdmin,
    async (requiresAdmin) => {
        if (!requiresAdmin) {
            authReady.value = true;
            return;
        }

        authReady.value = false;
        errorMessage.value = '';

        try {
            if (localStorage.getItem(tokenKey)) {
                admin.value = await apiRequest('/api/admin/me');
            } else {
                admin.value = null;
            }
        } catch (error) {
            if (error.status !== 401) {
                errorMessage.value = error.message;
            }
        } finally {
            authReady.value = true;
        }
    },
    { immediate: true },
);

async function loadSiteTitles() {
    try {
        const response = await fetch(
            '/api/web/site-settings?keys=web_title,web_admin_title',
            { headers: { Accept: 'application/json' } },
        );

        if (!response.ok) {
            return;
        }

        const result = await response.json();
        siteTitles.value = result.data ?? {};
        applySiteTitle();
    } catch {
        // 请求失败时保留默认标题。
    }
}

function saveToken(accessToken) {
    localStorage.setItem(tokenKey, accessToken);
    localStorage.setItem(tokenRotatedAtKey, String(Date.now()));
}

function clearJwtAuth() {
    localStorage.removeItem(tokenKey);
    localStorage.removeItem(tokenRotatedAtKey);
    admin.value = null;
}

function updateAdminProfile(updatedAdmin) {
    admin.value = updatedAdmin;
}

const routableMenuPaths = new Set(
    Object.values(pageRouteNames).map(
        (routeName) => router.resolve({ name: routeName }).path,
    ),
);

function selectPage(path) {
    if (routableMenuPaths.has(path)) {
        router.push(path);
    }
}

function showSuccessMessage(message) {
    successMessage.value = message;

    if (successMessageTimer !== null) {
        clearTimeout(successMessageTimer);
    }

    successMessageTimer = setTimeout(() => {
        clearSuccessMessage();
        successMessageTimer = null;
    }, 3000);
}

function clearSuccessMessage() {
    successMessage.value = '';

    if (successMessageTimer !== null) {
        clearTimeout(successMessageTimer);
        successMessageTimer = null;
    }
}

async function loadSiteTitle() {
    try {
        const response = await fetch(
            '/api/web/site-settings?keys=web_title',
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            return;
        }

        const result = await response.json();
        const title = result.data?.web_title;

        if (typeof title === 'string' && title.trim() !== '') {
            document.title = title.trim();
        }
    } catch {
        // 请求失败时保留 Blade 中的默认标题。
    }
}

onUnmounted(() => {
    if (successMessageTimer !== null) {
        clearTimeout(successMessageTimer);
    }
    window.removeEventListener('admin-auth-expired', handleAuthExpired);
});

onMounted(async () => {
    window.addEventListener('admin-auth-expired', handleAuthExpired);

    void loadSiteTitle();

    await router.isReady();

    void loadSiteTitles();
});

function handleAuthExpired() {
    clearJwtAuth();
}

async function login() {
    loading.value = true;
    errorMessage.value = '';

    try {
        // 适用于session模式
        // const csrfResponse = await fetch('/sanctum/csrf-cookie', {
        //     credentials: 'same-origin',
        // });
        //
        // if (!csrfResponse.ok) {
        //     errorMessage.value = '获取 CSRF Cookie 失败，请检查 Sanctum 路由配置。';
        //     return;
        // }
        //
        // admin.value = await apiRequest('/api/admin/login', {
        //     method: 'POST',
        //     body: JSON.stringify({
        //         email: email.value,
        //         password: password.value,
        //     }),
        // });

        // 适用于JWT模式
        const response = await apiRequest('/api/admin/login', {
            method: 'POST',
            body: JSON.stringify({
                email: email.value,
                password: password.value,
            }),
        });
        const { access_token: accessToken, ...adminData } = response;
        // sessionStorage.setItem('admin_access_token', accessToken);//默认为会按选项卡隔离
        // localStorage.setItem('admin_access_token', accessToken);//localStorage 会让 token 在多个标签间共享
        // saveToken(accessToken, response.expires_in);//保存token和过期时间，并设置刷新定时器
        saveToken(accessToken);
        admin.value = adminData;

        password.value = '';
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

async function sendAdminPasswordResetLink() {
    loading.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        const response = await apiRequest('/api/admin/forgot-password', {
            method: 'POST',
            body: JSON.stringify({ email: email.value }),
        });

        successMessage.value = response.message;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

// 适用于session模式
// async function logout() {
//     errorMessage.value = '';
//
//     try {
//         await apiRequest('/api/admin/logout', {
//             method: 'POST',
//         });
//
//         admin.value = null;
//     } catch (error) {
//         errorMessage.value = error.message;
//     }
// }

// 适用于JWT模式
async function logout() {
    errorMessage.value = '';

    try {
        await apiRequest('/api/admin/logout', {
            method: 'POST',
        });
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        clearJwtAuth();
    }
}
</script>

<template>
    <RouterView v-if="!route.meta.withAdmin" />
    <main v-else class="min-h-screen bg-slate-100 p-4 sm:p-6 text-slate-900">
        <section class="rounded-xl bg-white p-8 shadow">

            <template v-if="!authReady">
                <p>正在恢复登录状态…</p>
            </template>

            <template v-else-if="admin">
                <AdminLayout
                    :admin="admin"
                    :active-path="route.path"
                    @select-page="selectPage"
                    @change-password="showOwnPasswordForm = true"
                    @edit-profile="editOwnProfile"
                    @logout="logout" >

                    <OwnPasswordForm
                        v-if="showOwnPasswordForm"
                        @close="showOwnPasswordForm = false"
                        @success="showSuccessMessage"
                    />

                    <p
                        v-if="successMessage"
                        role="status"
                        class="fixed left-1/2 top-4 z-50 -translate-x-1/2 rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-700 shadow-lg"
                    >
                        {{ successMessage }}
                    </p>

                    <RouterView v-slot="{ Component }">
                        <component
                            :is="Component"
                            @profile-updated="updateAdminProfile"
                            ref="adminManagementRef"
                            v-bind="route.meta.withAdmin ? { admin } : {}"
                            @success="showSuccessMessage"
                        />
                    </RouterView>
                </AdminLayout>
            </template>

            <form v-else @submit.prevent="forgotPasswordMode ? sendAdminPasswordResetLink() : login()">
                <h1 class="text-2xl font-bold">
                    {{ forgotPasswordMode ? '找回管理员密码' : '管理员登录' }}
                </h1>

                <label class="mt-6 block">
                    <span class="text-sm font-medium">邮箱</span>
                    <input
                        v-model="email"
                        type="email"
                        autocomplete="username"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    >
                </label>

                <label v-if="!forgotPasswordMode" class="mt-4 block">
                    <span class="text-sm font-medium">密码</span>
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-1 w-full rounded border border-slate-300 px-3 py-2"
                    >
                </label>

                <p v-if="errorMessage" class="mt-4 text-sm text-red-600">
                    {{ errorMessage }}
                </p>
                <p v-if="successMessage" class="mt-4 text-sm text-emerald-700" role="status">
                    {{ successMessage }}
                </p>

                <button
                    type="submit"
                    :disabled="loading"
                    class="mt-6 w-full rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                >
                    {{ loading ? '处理中…' : forgotPasswordMode ? '发送重置邮件' : '登录' }}
                </button>

                <button
                    v-if="forgotPasswordMode"
                    type="button"
                    class="mt-4 text-sm text-blue-700 hover:underline"
                    @click="forgotPasswordMode = false; errorMessage = ''; successMessage = ''"
                >
                    返回登录
                </button>
                <button
                    v-else
                    type="button"
                    class="mt-4 text-sm text-blue-700 hover:underline"
                    @click="forgotPasswordMode = true; errorMessage = ''; successMessage = ''"
                >
                    忘记密码？
                </button>
            </form>

        </section>
    </main>
</template>
