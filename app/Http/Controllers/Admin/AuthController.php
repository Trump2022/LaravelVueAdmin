<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function sendPasswordResetLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        Password::broker('admins')->sendResetLink($data);

        return response()->json([
            'message' => '如果该邮箱对应管理员账号，重置邮件将发送。',
        ], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('admins')->reset(
            $data,
            function (Admin $admin, string $password): void {
                $admin->forceFill(['password' => $password])->save();
                event(new PasswordReset($admin));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => '密码已重置，请使用新密码登录。',
        ]);
    }

    /**
     * 登录 (适用于guards的driver不是JWT是session)
     */
    public function loginAsSession(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误。'],
            ]);
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin->status) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误。'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json($this->adminData($admin));
    }

    /**
     * 刷新 JWT Token
     */
    public function refresh(): JsonResponse
    {
        $guard = Auth::guard('admin');

        try {
            $token = $guard->refresh();
        } catch (JWTException $exception) {
            return response()->json([
                'message' => '登录已过期，请重新登录。',
            ], 401);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $guard->getTTL() * 60,
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $token = Auth::guard('admin')->attempt($credentials);

        if ($token === false) {
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误。'],
            ]);
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin->status) {
            Auth::guard('admin')->logout();

            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误。'],
            ]);
        }

        return response()->json(array_merge(
            $this->adminData($admin),
            [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => Auth::guard('admin')->getTTL() * 60,
            ],
        ));
    }

    /**
     * 登出 (适用于guards的driver不是JWT是session)
     */
    public function logoutAsSession(Request $request): JsonResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->regenerate();

        return response()->json([
            'message' => '已退出登录。',
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::guard('admin')->logout();

        return response()->json([
            'message' => '已退出登录。',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        return response()->json($this->adminData($admin));
    }

    public function updateOwnProfile(Request $request): JsonResponse
    {
        $actor = $request->user('admin');
        $validated = Admin::getValidate($request, $actor);
        // if (array_key_exists('email', $validated)) {
        //     if (Admin::checkEmailExists($validated, $actor, true)) {
        //         throw ValidationException::withMessages([
        //             'email' => ['该邮箱已被使用。'],
        //         ]);
        //     }
        // }

        $actor->update($validated);
        $actor->refresh();

        return response()->json([
            'data' => $this->adminData($actor),
        ]);
    }

    public function updateOwnPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = $request->user('admin');

        if (! Hash::check($validated['current_password'], $admin->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['当前密码不正确。'],
            ]);
        }

        $admin->password = $validated['password'];
        $admin->save();

        return response()->json([
            'message' => '密码修改成功。',
        ]);
    }

    private function adminData(Admin $admin): array
    {
        $admin->loadMissing('roles:id,code');

        $roleCodes = $admin->roles->pluck('code')->filter()->values()->all();
        $roleIds = $admin->roles
            ->modelKeys();

        $permissionCodes = [
            'admin.admins.role-options:get',
            'admin.roles.permissions.index:get',
            'admin.roles.permissions.assign:put',
            'admin.roles.permissions.revoke:delete',
            'admin.admins.update:patch',
            'admin.admins.password:patch',
            'admin.admins.index:get',
            'admin.roles.update:patch',
            'admin.roles.store:post',
            'admin.admins.store:post',
            'admin.permissions.store:post',
            'admin.admins.destroy:delete',
            'admin.roles.destroy:delete',
            'admin.users.index:get',
            'admin.users.store:post',
            'admin.users.show:get',
            'admin.users.update:patch',
            'admin.users.password:patch',
            'admin.users.destroy:delete',
            'admin.settings.index:get',
            'admin.settings.store:post',
            'admin.settings.show:get',
            'admin.settings.update:put',
            'admin.settings.destroy:delete',
        ];

        $permissions = Permission::query()
            ->where('type', 3)
            ->whereIn('permission_code', $permissionCodes)
            ->with('roles:id')
            ->get()
            ->keyBy('permission_code');

        $isDeveloper = in_array(Role::DEVELOPER_CODE, $roleCodes, true);
        $isSuperAdmin = in_array(Role::SUPER_ADMIN_CODE, $roleCodes, true);

        $hasApiPermission = function (string $permissionCode) use ($isDeveloper, $isSuperAdmin, $roleIds, $permissions): bool {
            if ($isDeveloper) {
                return true;
            }

            $permission = $permissions->get($permissionCode);

            if ($permission === null || $permission->developer_only) {
                return false;
            }

            if ($isSuperAdmin) {
                return true;
            }

            if ((int) $permission->status !== 1) {
                return false;
            }

            if (! $permission->authorization_required) {
                return true;
            }

            return $permission->roles->contains(
                fn (Role $role): bool => in_array($role->getKey(), $roleIds, true),
            );
        };

        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role_codes' => $roleCodes,
            'can_view_admin_role_options' => $hasApiPermission('admin.admins.role-options:get'),
            'can_manage_role_permissions' => $hasApiPermission('admin.roles.permissions.index:get') && $hasApiPermission('admin.roles.permissions.assign:put') && $hasApiPermission('admin.roles.permissions.revoke:delete'),
            'can_update_admins' => $hasApiPermission('admin.admins.update:patch'),
            'can_view_admins' => $hasApiPermission('admin.admins.index:get'),
            'can_reset_admin_passwords' => $hasApiPermission('admin.admins.password:patch'),
            'can_update_roles' => $hasApiPermission('admin.roles.update:patch'),
            'can_create_roles' => $hasApiPermission('admin.roles.store:post'),
            'can_create_admins' => $hasApiPermission('admin.admins.store:post'),
            'can_create_permissions' => $hasApiPermission('admin.permissions.store:post'),
            'can_delete_admins' => $hasApiPermission('admin.admins.destroy:delete'),
            'can_delete_roles' => $hasApiPermission('admin.roles.destroy:delete'),
            'can_view_users' => $hasApiPermission('admin.users.index:get'),
            'can_create_users' => $hasApiPermission('admin.users.store:post'),
            'can_view_user_details' => $hasApiPermission('admin.users.show:get'),
            'can_update_users' => $hasApiPermission('admin.users.update:patch'),
            'can_reset_user_passwords' => $hasApiPermission('admin.users.password:patch'),
            'can_delete_users' => $hasApiPermission('admin.users.destroy:delete'),
            'can_view_settings' => $hasApiPermission('admin.settings.index:get'),
            'can_create_settings' => $hasApiPermission('admin.settings.store:post'),
            'can_view_setting_details' => $hasApiPermission('admin.settings.show:get'),
            'can_update_settings' => $hasApiPermission('admin.settings.update:put'),
            'can_delete_settings' => $hasApiPermission('admin.settings.destroy:delete'),
        ];
    }
}
