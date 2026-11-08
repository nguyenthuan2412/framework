<?php

namespace Ryra\Core\Permission;

use Ryra\Core\Database\DB;

trait HasPermission
{
    protected $hasPermission = -1;

    public function can(string $permission): bool
    {
        if ($this->hasPermission === -1) {
            $this->hasPermission = DB::query()->table('admin_roles')
                ->join('role_permissions', 'admin_roles.role_id', 'role_permissions.id')
                ->where('admin_id', $this->getKey())
                ->where('role_permissions.permission_id', $permission)
                ->exists();
        }

        return $this->hasPermission;
    }
}