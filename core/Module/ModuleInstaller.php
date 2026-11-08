<?php

namespace Ryra\Core\Module;

use Ryra\Core\Container\ServiceProviderManager;
use Ryra\Core\App;

class ModuleInstaller
{
    public function __construct(
        protected ModuleManager $modules,
        protected ServiceProviderManager $serviceProviderManager
    ) {}

    /**
     * Cài đặt module cho ứng dụng.
     */
    public function install(): void
    {
        $modules = $this->buildModules();

        foreach ($modules as $module) {
            $this->modules->add($module->getId(), $module);
            $this->registerProvidersInModule($module);

            $functionExtends = $this->modules->getExtends();
            foreach ($functionExtends as $key => $func) {
                $config = $module->getConfig($key);
                if (!empty($config)) {
                    $func($config, $module);
                }
            }

            foreach ($this->modules->getAfterInstall() as $func) {
                $func($module);
            }
        }
    }

    protected function buildModules(): array
    {
        $modules = [];
        $pathModule = join_paths(App::root(), 'modules');
        $pathModule = escapeshellarg($pathModule);
        exec("find $pathModule -type f -name '*.module.php'", $files);
        if (!empty($files)) {
            foreach ($files as $fileModule) {
                $module = $this->buildModule($fileModule);
                if ($module) {
                    $modules[] = $module;
                }
            }
        }

        return $modules;
    }

    protected function buildModule(string $fileModule): ?Module
    {
        $folderName = basename(dirname($fileModule));
        $fileName = basename($fileModule, ".module.php");
        if ($folderName === $fileName) {
            $module = new Module($fileModule);
            if ($module->getName()) {
                return $module;
            }
        }

        return null;
    }

    protected function getCacheModule()
    {
        return storage_path(join_paths('cache', 'modules'));
    }

    protected function registerProvidersInModule(Module $module): void
    {
        foreach ($module->providers() as $provider) {
            $this->serviceProviderManager->register($provider);
            $this->serviceProviderManager->boot($provider);
        }
    }
}