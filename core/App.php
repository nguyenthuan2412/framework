<?php

namespace Ryra\Core;

use Ryra\Core\Container\Container;
use Ryra\Core\Container\Proxy\ProxyManager;
use Closure;

class App extends Container
{
    const NAME = 'Ryra framework';

    const VERSION = '0.1';

    protected ?Closure $withMiddleware = null;

    protected ?Closure $withProviders = null;

    protected ?Closure $terminateRequest = null;

    protected static ?App $instance = null;

    protected ?string $basePath;

    public static function getInstance(): static
    {
        if (\is_null(static::$instance)) {
            \Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

            static::$instance = new static;
            static::$instance->singleton('container', function () {
                return static::$instance;
            });
            static::$instance->alias('container', \Ryra\Core\Contracts\Container\Container::class);
            static::$instance->alias('container', static::class);
        }

        return static::$instance;
    }

    /**
     * @template T
     * @param class-string<T> $id
     * @return T
     */
    public static function service(string $id): mixed
    {
        return static::getInstance()->get($id);
    }

    public static function hasService(string $id): bool
    {
        return static::getInstance()->has($id);
    }

    public function getCoreVersion(): string
    {
        return static::VERSION;
    }

    public function getCoreName(): string
    {
        return static::NAME;
    }

    /**
     * Lưu đường dẫn chạy application.
     */
    public function setBasePath(string $path): static
    {
        if (\chdir($path)) {
            $this->basePath = $path;
            // Proxy sinh sẵn cho Container::make()/injectProperties(), thay cho Reflection mỗi lần gọi.
            static::setProxyManager(ProxyManager::create(\join_paths($path, 'storage', 'runtime', 'proxy')));
        }
        return $this;
    }

    /**
     * Lấy đường dẫn chạy application.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Lấy đường dẫn thư mục làm việc hiện tại.
     */
    public static function root(): string
    {
        return static::getInstance()->getBasePath();
    }

    public static function baseUrl(): string
    {
        return \config('app.url');
    }

    /**
     * Set the current locale for this coroutine/request.
     *
     * @param string $locale
     * @return void
     */
    public static function setLocale(string $locale): void
    {
        App::service('translator')->setLocale($locale);
    }

    /**
     * Get the current locale.
     *
     * @return string
     */
    public static function getLocale(): string
    {
        return App::service('translator')->getLocale();
    }

    public static function environment(string|array|null $environment = null): bool|string
    {
        $appEnv = $_SERVER['APP_ENV'] ?? 'local';
        if (\is_array($environment)) {
            return \in_array($appEnv, $environment);
        }

        if (\is_string($environment)) {
            return $appEnv === $environment;
        }

        return $appEnv;
    }

    public static function environmentFile(): string
    {
        $environment = static::environment();
        return $environment == 'local' ? '.env' : ".env.$environment";
    }

    public static function isProduction(): bool
    {
        return static::environment('production');
    }

    public static function isLocal(): bool
    {
        return static::environment('local');
    }

    public static function isStaging(): bool
    {
        return static::environment('staging');
    }

    /**
     * Lấy đường dẫn của config.
     */
    public function getConfigPath(): string
    {
        return \join_paths($this->basePath, 'config');
    }

    /**
     * Lấy đường dẫn của storage bạn đang sử dụng.
     */
    public function storagePath(?string $path = null): string
    {
        if (\is_null($path)) {
            return \join_paths($this->basePath, 'storage');
        }

        return \join_paths($this->basePath, 'storage', ltrim($path, DS));
    }
    
    /**
     * Đăng ký global middleware
     */
    public function withMiddleware(Closure $callback): static
    {
        $this->withMiddleware = $callback;
        return $this;
    }

    /**
     * Đăng ký các provider mặc định
     */
    public function withProviders(Closure $callback): static
    {
        $this->withProviders = $callback;
        return $this;
    }

    /**
     * Khởi tạo console application.
     */
    public function start(): int
    {
        $this->bootServiceProviders();
        $this->registerGlobalMiddlewares();
        return $this->get('console')->run();
    }

    public function terminateRequest(Closure $callback): static
    {
        $this->terminateRequest = $callback;
        return $this;
    }

    public function getTerminateRequest(): ?Closure
    {
        return $this->terminateRequest;
    }

    protected function registerGlobalMiddlewares(): void
    {
        if (!empty($callback = $this->withMiddleware)) {
            $callback($this->get('middleware.collection'));
        }
    }

    /**
     * Đăng ký các service provider và khỏi động nó.
     */
    public function bootServiceProviders(): void
    {
        $providers = require 'providers.php';
        $providerManager = $this->get('service_provider_manager');

        if (!empty($callback = $this->withProviders)) {
            $callback($providerManager);
        }
        
        foreach ($providers as $provider) {
            $providerManager->register($provider);
        }
        $providerManager->boot();
    }
}