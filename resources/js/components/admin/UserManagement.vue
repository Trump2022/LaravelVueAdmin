<script setup>
import { onMounted, ref } from 'vue';
import { apiRequest } from '../../api';

const users = ref([]);
const loading = ref(false);
const errorMessage = ref('');
const search = ref('');
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
const showCreateForm = ref(false);
const creating = ref(false);

const showEditDialog = ref(false);
const savingEdit = ref(false);
const editingUserId = ref(null);
const editErrorMessage = ref('');
const editForm = ref({ name: '', email: '' });
const deletingUserId = ref(null);

const showPasswordDialog = ref(false);
const savingPassword = ref(false);
const resettingUserId = ref(null);
const passwordErrorMessage = ref('');
const passwordForm = ref({ password: '', password_confirmation: '' });

const props = defineProps({
    admin: {
        type: Object,
        required: true,
    },
});

function startEditing(user) {
    editingUserId.value = user.id;
    editForm.value = { name: user.name, email: user.email };
    editErrorMessage.value = '';
    showEditDialog.value = true;
}

function cancelEdit() {
    showEditDialog.value = false;
    editingUserId.value = null;
    editForm.value = { name: '', email: '' };
    editErrorMessage.value = '';
}

async function updateUser() {
    savingEdit.value = true;
    editErrorMessage.value = '';

    try {
        await apiRequest(`/api/admin/users/${editingUserId.value}`, {
            method: 'PATCH',
            body: JSON.stringify(editForm.value),
        });

        cancelEdit();
        await loadUsers();
    } catch (error) {
        editErrorMessage.value = error.message;
    } finally {
        savingEdit.value = false;
    }
}

function startResetPassword(user) {
    resettingUserId.value = user.id;
    passwordForm.value = { password: '', password_confirmation: '' };
    passwordErrorMessage.value = '';
    showPasswordDialog.value = true;
}

function cancelResetPassword() {
    showPasswordDialog.value = false;
    resettingUserId.value = null;
    passwordForm.value = { password: '', password_confirmation: '' };
    passwordErrorMessage.value = '';
}

async function updatePassword() {
    savingPassword.value = true;
    passwordErrorMessage.value = '';

    try {
        await apiRequest(`/api/admin/users/${resettingUserId.value}/password`, {
            method: 'PATCH',
            body: JSON.stringify(passwordForm.value),
        });

        cancelResetPassword();
    } catch (error) {
        passwordErrorMessage.value = error.message;
    } finally {
        savingPassword.value = false;
    }
}

async function deleteUser(user) {
    if (!window.confirm(`确定删除用户“${user.name}”吗？`)) {
        return;
    }

    deletingUserId.value = user.id;
    errorMessage.value = '';

    try {
        await apiRequest(`/api/admin/users/${user.id}`, {
            method: 'DELETE',
        });

        const page = users.value.length === 1 && currentPage.value > 1
            ? currentPage.value - 1
            : currentPage.value;

        await loadUsers(page);
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        deletingUserId.value = null;
    }
}

function emptyCreateForm() {
    return { name: '', email: '', password: '' };
}

const createForm = ref(emptyCreateForm());

function cancelCreate() {
    showCreateForm.value = false;
    createForm.value = emptyCreateForm();
}

async function createUser() {
    creating.value = true;
    errorMessage.value = '';

    try {
        await apiRequest('/api/admin/users', {
            method: 'POST',
            body: JSON.stringify(createForm.value),
        });

        cancelCreate();
        await loadUsers(1);
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        creating.value = false;
    }
}

async function loadUsers(page = currentPage.value) {
    loading.value = true;
    errorMessage.value = '';
    currentPage.value = page;

    const params = new URLSearchParams({
        page: String(page),
        per_page: String(perPage.value),
        search: search.value.trim(),
    });

    try {
        const response = await apiRequest(`/api/admin/users?${params}`);
        users.value = response.data;
        currentPage.value = response.current_page;
        total.value = response.total;
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        loading.value = false;
    }
}

function changePageSize(size) {
    perPage.value = size;
    loadUsers(1);
}

onMounted(() => {
    if (props.admin.can_view_users) {
        loadUsers();
    }
});

</script>

<template>
    <section class="mt-6 w-full rounded-xl bg-white p-6 shadow">

        <p v-if="errorMessage" class="mb-4 text-sm text-red-600">
            {{ errorMessage }}
        </p>

        <div class="mb-4 flex flex-wrap items-start gap-y-2">
            <el-button
                v-if="props.admin.can_create_users"
                type="primary"
                class="mr-auto"
                @click="showCreateForm = !showCreateForm"
            >
                {{ showCreateForm ? '收起' : '新增用户' }}
            </el-button>

            <div class="mr-2 w-64 max-w-full">
                <el-input
                    v-model="search"
                    @keyup.enter="loadUsers(1)"
                    clearable placeholder="搜索姓名或邮箱"
                />
            </div>

            <el-button
                type="primary"
                class="ml-auto"
                @click="loadUsers(1)"
                >
                搜索
            </el-button>
        </div>



        <form
            v-if="showCreateForm"
            class="mb-6 grid gap-4 rounded-lg border border-slate-200 p-4 sm:grid-cols-2 lg:grid-cols-4"
            @submit.prevent="createUser"
        >
            <label>
                <span class="text-sm font-medium">姓名</span>
                <el-input
                    v-model="createForm.name"
                    type="text"
                    maxlength="255"
                    autocomplete="name"
                    required
                />
            </label>

            <label>
                <span class="text-sm font-medium">邮箱</span>
                <el-input
                    v-model="createForm.email"
                    type="email"
                    maxlength="255"
                    autocomplete="email"
                    required
                />
            </label>

            <label>
                <span class="text-sm font-medium">初始密码（至少 8 位）</span>
                <el-input
                    v-model="createForm.password"
                    type="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                />
            </label>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                <el-button type="primary" native-type="submit" :loading="creating">
                    {{ creating ? '创建中…' : '新增用户' }}
                </el-button>
                <el-button native-type="button" :disabled="creating" @click="cancelCreate">
                    取消
                </el-button>
            </div>
        </form>

        <p v-if="loading" class="text-slate-600">
            正在加载用户列表…
        </p>

        <p v-else-if="users.length === 0" class="text-slate-600">
            暂无用户数据。
        </p>

        <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-left text-sm">
                <thead class="border-b text-slate-500">
                <tr>
                    <th class="px-3 py-3">ID</th>
                    <th class="px-3 py-3">姓名</th>
                    <th class="px-3 py-3">邮箱</th>
                    <th class="px-3 py-3">注册时间</th>
                    <th class="px-3 py-3">操作</th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="item in users"
                    :key="item.id"
                    class="border-b last:border-0" >

                    <td class="px-3 py-3">{{ item.id }}</td>
                    <td class="px-3 py-3">{{ item.name }}</td>
                    <td class="px-3 py-3">{{ item.email }}</td>
                    <td class="px-3 py-3">{{ item.created_at }}</td>

                    <td class="px-3 py-3">
                        <el-button v-if="props.admin.can_update_users" link type="primary" @click="startEditing(item)">编辑</el-button>

                        <el-button v-if="props.admin.can_reset_user_passwords" link type="warning" @click="startResetPassword(item)">
                            重置密码
                        </el-button>

                        <el-button
                            v-if="props.admin.can_delete_users"
                            link
                            type="danger"
                            :loading="deletingUserId === item.id"
                            @click="deleteUser(item)"
                            >
                            删除
                        </el-button>
                    </td>

                </tr>
                </tbody>
            </table>

            <el-dialog v-model="showEditDialog" title="编辑用户" width="460px">
                <form class="space-y-4" @submit.prevent="updateUser">
                    <el-alert
                        v-if="editErrorMessage"
                        :title="editErrorMessage"
                        type="error"
                        :closable="false"
                    />

                    <el-input v-model="editForm.name" placeholder="姓名" required maxlength="255" />
                    <el-input v-model="editForm.email" type="email" placeholder="邮箱" required maxlength="255" />

                    <div class="flex justify-end gap-2">
                        <el-button :disabled="savingEdit" @click="cancelEdit">取消</el-button>
                        <el-button type="primary" native-type="submit" :loading="savingEdit">保存</el-button>
                    </div>
                </form>
            </el-dialog>

            <el-dialog v-model="showPasswordDialog" title="重置用户密码" width="460px">
                <form class="space-y-4" @submit.prevent="updatePassword">
                    <el-alert
                        v-if="passwordErrorMessage"
                        :title="passwordErrorMessage"
                        type="error"
                        :closable="false"
                    />

                    <el-input
                        v-model="passwordForm.password"
                        type="password"
                        placeholder="新密码，至少 8 位"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    />
                    <el-input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        placeholder="再次输入新密码"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    />

                    <div class="flex justify-end gap-2">
                        <el-button :disabled="savingPassword" @click="cancelResetPassword">取消</el-button>
                        <el-button type="primary" native-type="submit" :loading="savingPassword">确认重置</el-button>
                    </div>
                </form>
            </el-dialog>

            <el-pagination
                v-if="total > 0"
                class="mt-4 justify-end"
                :current-page="currentPage"
                :page-size="perPage"
                :page-sizes="[10, 20, 50]"
                :total="total"
                layout="total, sizes, prev, pager, next, jumper"
                @current-change="loadUsers"
                @size-change="changePageSize"
            />
        </div>

    </section>

<!--    <section class="space-y-4">-->

<!--        <el-button type="primary" @click="showCreateForm = !showCreateForm">-->
<!--            {{ showCreateForm ? '收起' : '新增用户' }}-->
<!--        </el-button>-->

<!--        <header class="flex flex-wrap items-center justify-between gap-3">-->
<!--            <h1 class="text-xl font-semibold">用户列表</h1>-->

<!--            <div class="flex gap-2">-->
<!--                <el-input-->
<!--                    v-model="search"-->
<!--                    clearable-->
<!--                    placeholder="搜索姓名或邮箱"-->
<!--                    @keyup.enter="loadUsers(1)"-->
<!--                />-->
<!--                <el-button type="primary" @click="loadUsers(1)">搜索</el-button>-->
<!--            </div>-->
<!--        </header>-->

<!--        <form-->
<!--            v-if="showCreateForm"-->
<!--            class="grid gap-3 rounded border border-slate-200 p-4 sm:grid-cols-3"-->
<!--            @submit.prevent="createUser"-->
<!--        >-->
<!--            <el-input v-model="createForm.name" placeholder="姓名" required maxlength="255" />-->
<!--            <el-input v-model="createForm.email" type="email" placeholder="邮箱" required maxlength="255" />-->
<!--            <el-input v-model="createForm.password" type="password" placeholder="初始密码，至少 8 位" required minlength="8" autocomplete="new-password" />-->

<!--            <div class="flex gap-2 sm:col-span-3">-->
<!--                <el-button type="primary" native-type="submit" :loading="creating">创建</el-button>-->
<!--                <el-button :disabled="creating" @click="cancelCreate">取消</el-button>-->
<!--            </div>-->
<!--        </form>-->

<!--        <el-alert-->
<!--            v-if="errorMessage"-->
<!--            :title="errorMessage"-->
<!--            type="error"-->
<!--            :closable="false"-->
<!--        />-->

<!--        <el-table :data="users" v-loading="loading" row-key="id">-->
<!--            <el-table-column prop="id" label="ID" width="90" />-->
<!--            <el-table-column prop="name" label="姓名" min-width="160" />-->
<!--            <el-table-column prop="email" label="邮箱" min-width="240" />-->
<!--            <el-table-column prop="created_at" label="注册时间" min-width="180" />-->
<!--        </el-table>-->

<!--        <el-pagination-->
<!--            v-model:current-page="currentPage"-->
<!--            v-model:page-size="perPage"-->
<!--            :page-sizes="[10, 20, 50]"-->
<!--            :total="total"-->
<!--            layout="total, sizes, prev, pager, next"-->
<!--            @current-change="loadUsers"-->
<!--            @size-change="changePageSize"-->
<!--        />-->
<!--    </section>-->
</template>
