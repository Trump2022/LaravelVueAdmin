<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /**
     * @var string
     */
    public const DEVELOPER_CODE = 'developer';

    /**
     * @var string
     */
    public const SUPER_ADMIN_CODE = 'super_admin';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    /**
     * 关联管理员
     *
     * @return BelongsToMany
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'admin_role', 'role_id', 'admin_id');
    }

    /**
     * 关联权限
     *
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role', 'role_id', 'permission_id');
    }
}
