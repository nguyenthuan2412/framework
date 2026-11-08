<?php

namespace Ryra\Core\Permission;

class PermissionManager
{
    protected array $permissions = [];

    public function addPermission(string $id, string $name, string $description, string $module): void
    {
        $this->permissions[$id] = [
            'name' => $name,
            'description' => $description,
            'module' => $module,
        ];
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }
}