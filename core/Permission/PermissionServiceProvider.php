<?php

namespace Ryra\Core\Permission;

use Ryra\Core\Container\ServiceProvider;

class PermissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('permissions', fn () => new PermissionManager());
    }

    public function boot(): void
    {
        $modules = $this->app->get('module.manager');
        $permissionManager = $this->app->get('permissions');
        foreach ($modules as $module) {
            $pathModule = $module->getPath();
            $nameModule = $module->getName();
            $filePermission = join_paths($pathModule, "$nameModule.permission.php");
            if (file_exists($filePermission)) {
                $permissions = require_once $filePermission;
                if (is_array($permissions)) {
                    foreach ($permissions as $key => $item) {
                        $permissionManager->addPermission($key, $item['title'], $item['description'] ?? '', $nameModule);
                    }
                }
            }
        }
    }
}