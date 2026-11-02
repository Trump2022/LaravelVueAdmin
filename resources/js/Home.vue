<script setup>
import { onMounted, ref } from 'vue';
import WebBottomNav from './components/web/WebBottomNav.vue';
import { getUserToken, userApiRequest } from './userApi';
import defaultAvatar from '../images/default-avatar.jpg';

const user = ref(null);
const authStatus = ref('loading');

onMounted(async () => {
    if (!getUserToken()) {
        authStatus.value = 'guest';
        return;
    }

    try {
        const response = await userApiRequest('/api/auth/me');
        user.value = response.user;
        authStatus.value = 'authenticated';
    } catch (error) {
        authStatus.value = error.status === 401 ? 'guest' : 'error';
    }
});
</script>

<template>
    <main class="min-h-screen bg-[#f3f5f2] pb-24 text-[#17231f] md:pb-0">
        <header class="hidden border-b border-[#d6ded8] bg-white md:block">
            <nav class="mx-auto flex h-16 max-w-7xl items-center px-8">
                <a
                    href="/web"
                    class="font-semibold text-[#17231f] hover:text-emerald-800"
                >
                    首页
                </a>

                <div class="ml-auto">
                    <span
                        v-if="authStatus === 'loading'"
                        class="block size-10 rounded-full bg-slate-100"
                        aria-label="正在加载账户"
                    />

                    <RouterLink
                        v-else-if="authStatus === 'authenticated'"
                        to="/web/account"
                        aria-label="我的账户"
                        class="block rounded-full focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2"
                    >
                        <img
                            :src="defaultAvatar"
                            alt="用户头像"
                            class="size-10 rounded-full object-cover"
                        >
                    </RouterLink>

                    <RouterLink
                        v-else-if="authStatus === 'guest'"
                        to="/web/account"
                        class="text-sm font-medium text-emerald-900 hover:underline"
                    >
                        登录 / 注册
                    </RouterLink>

                    <span v-else class="text-sm text-slate-600">
                        账户暂时无法加载
                    </span>
                </div>
            </nav>
        </header>

        <section class="border-b border-[#d6ded8] bg-[#e6ede8]">
            <div class="mx-auto max-w-7xl px-6 py-20 sm:px-10 sm:py-28">
                <p class="text-sm font-medium text-emerald-800">
                    一个轻巧的生活空间
                </p>

                <h1 class="mt-5 max-w-3xl text-4xl font-semibold leading-tight sm:text-6xl">
                    给日常留一点余地。
                </h1>

                <p class="mt-5 max-w-xl leading-7 text-slate-700">
                    从这里整理你的信息，管理自己的账户，让重要的事情清楚、简单。
                </p>

                <RouterLink
                    to="/web/account"
                    class="mt-8 inline-flex rounded bg-emerald-900 px-5 py-3 text-sm font-medium text-white hover:bg-emerald-800"
                >
                    {{ user ? '查看我的账户' : '登录 / 创建账户' }}
                </RouterLink>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-6 py-10 sm:px-10">
            <h2 class="text-lg font-semibold">从账户开始</h2>
            <p class="mt-2 max-w-xl leading-7 text-slate-600">
                登录后可以查看和更新个人信息，也可以随时修改账户密码。
            </p>
        </section>

        <WebBottomNav />
    </main>
</template>
