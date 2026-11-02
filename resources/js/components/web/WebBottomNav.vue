<script setup>
import { House, User } from '@element-plus/icons-vue';
</script>

<template>
    <nav
        aria-label="前台主导航"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-[#d6ded8] bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-2px_12px_rgb(23_35_31_/_0.06)] backdrop-blur md:hidden"
    >
        <div class="mx-auto grid max-w-xl grid-cols-2">
            <RouterLink
                to="/web"
                exact-active-class="text-emerald-900"
                class="flex min-h-16 flex-col items-center justify-center gap-1 text-slate-500"
            >
                <House class="size-5" aria-hidden="true" />
                <span class="text-xs">首页</span>
            </RouterLink>

            <RouterLink
                to="/web/account"
                exact-active-class="text-emerald-900"
                class="flex min-h-16 flex-col items-center justify-center gap-1 text-slate-500"
            >
                <User class="size-5" aria-hidden="true" />
                <span class="text-xs">我的</span>
            </RouterLink>
        </div>
    </nav>
</template>