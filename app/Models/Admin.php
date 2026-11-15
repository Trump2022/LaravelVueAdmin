<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rule;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable implements JWTSubject
{
    use Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => 'boolean',
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * 管理员角色关联
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_role', 'admin_id', 'role_id');
    }

    /**
     * 检查管理员是否是超级管理员
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->roles()
            ->where('code', Role::SUPER_ADMIN_CODE)
            ->exists();
    }

    /**
     * 检查管理员是否是开发者
     *
     * @return bool
     */
    public function isDeveloper(): bool
    {
        return $this->roles()
            ->where('code', Role::DEVELOPER_CODE)
            ->exists();
    }

    /**
     * 检查管理员是否有API权限
     *
     * @param string $routeName
     * @param string $method
     * @return bool
     */
    public function hasApiPermission(string $routeName, string $method): bool
    {
        $this->loadMissing('roles:id,code');

        $actorIsDeveloper = $this->roles->contains('code', Role::DEVELOPER_CODE);

        if ($actorIsDeveloper) {
            return true;
        }

        $permissionCode = $routeName.':'.strtolower($method);

        $permission = Permission::query()
            ->where('permission_code', $permissionCode)
            ->where('type', 3)
            ->first();

        if ($permission === null || $permission->developer_only) {
            return false;
        }

        if ($this->roles->contains('code', Role::SUPER_ADMIN_CODE)) {
            return true;
        }

        if ((int) $permission->status !== 1) {
            return false;
        }

        if (! $permission->authorization_required) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', function (Builder $query) use ($permission): void {
                $query->whereKey($permission->getKey());
            })
            ->exists();
    }

    /**
     * 验证数据
     *
     * @param Request $request
     * @param Admin $admin
     * @return array
     */
    public static function getValidate(Request $request, Admin $admin): array
    {
        return $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->ignore($admin->getKey()),
            ],
        ], [
            'email.unique' => '该邮箱已被使用。',
        ]);
    }

    /**
     * email是否存在
     *
     * @param  bool  $isEdit  | 为true是编辑
     */
    public static function checkEmailExists(array $validated, ?Admin $admin = null, bool $isEdit = false): bool
    {
        return Admin::query()
            ->where('email', $validated['email'])
            ->when(
                $isEdit,
                fn (Builder $query) => $query->where('id', '!=', $admin->getKey()),
            )
            ->exists();
    }
}
