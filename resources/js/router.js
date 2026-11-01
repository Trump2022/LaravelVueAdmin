import Dashboard from './components/admin/Dashboard.vue';
import { createRouter, createWebHistory } from 'vue-router';
import AdminManagement from './components/admin/AdminManagement.vue';
import RoleManagement from './components/admin/RoleManagement.vue';
import PermissionManagement from './components/admin/PermissionManagement.vue';
import SettingManagement from './components/admin/SettingManagement.vue';
import UserManagement from './components/admin/UserManagement.vue';
import Home from './Home.vue';

export const pageRouteNames = {
    dashboard: 'admin-dashboard',
    admins: 'admin-admins',
    roles: 'admin-roles',
    permissions: 'admin-permissions',
    settings: 'admin-settings',
    users: 'admin-users',
};

export default createRouter({
    history: createWebHistory('/'),
    routes: [
        {
            path: '/',
            redirect: { name: 'web-home' },
        },
        {
            path: '/web',
            name: 'web-home',
            component: Home,
        },
        {
            path: '/admin',
            redirect: { name: pageRouteNames.dashboard },
        },
        {
            path: '/admin/dashboard',
            name: pageRouteNames.dashboard,
            component: Dashboard,
            meta: {
                page: 'dashboard',
                title: '后台仪表盘',
                withAdmin: true,
            },
        },
        {
            path: '/admin/admins',
            name: pageRouteNames.admins,
            component: AdminManagement,
            meta: { page: 'admins', title: '管理员管理', withAdmin: true },
        },
        {
            path: '/admin/roles',
            name: pageRouteNames.roles,
            component: RoleManagement,
            meta: { page: 'roles', title: '角色管理', withAdmin: true },
        },
        {
            path: '/admin/permissions',
            name: pageRouteNames.permissions,
            component: PermissionManagement,
            meta: {
                page: 'permissions',
                title: '菜单管理' ,
                withAdmin: true,
            },
        },
        {
            path: '/admin/settings',
            name: pageRouteNames.settings,
            component: SettingManagement,
            meta: {
                page: 'settings',
                title: '通用设置',
                withAdmin: true,
            },
        },
        {
            path: '/admin/users',
            name: pageRouteNames.users,
            component: UserManagement,
            meta: { page: 'users', title: '用户列表', withAdmin: true },
        },

        {
            path: '/admin/:pathMatch(.*)*',
            redirect: { name: pageRouteNames.dashboard },//所有未匹配的/admin/开头的路径都重定向到仪表盘
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'web-home' },//其它未匹配的路径都去首页
        },
    ],
});
