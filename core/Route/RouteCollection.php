<?php

namespace Ryra\Core\Route;

use Ryra\Core\Route\Attributes\Delete;
use Ryra\Core\Route\Attributes\Route;
use Ryra\Core\Route\Attributes\Patch;
use Ryra\Core\Route\Attributes\Head;
use Ryra\Core\Route\Attributes\Post;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Route\Attributes\Put;
use FastRoute\RouteCollector;
use FastRoute\DataGenerator;
use FastRoute\RouteParser;
use ReflectionMethod;
use ReflectionClass;
use Ryra\Core\App;
use Ryra\Core\Module\Module;
use Ryra\Core\Route\Attributes\Group;
use Ryra\Core\Route\Attributes\Version;
use Ryra\Core\Route\Versioning\VersionedRoutes;
use Ryra\Core\Route\Versioning\VersionResolver;

class RouteCollection extends RouteCollector implements RouteCollectionInterfade
{
    /**
     * Url của các route có tên, dạng [name => [version => uri]].
     * Key version rỗng là route version neutral.
     */
    protected array $collection = [];

    /**
     * Các route đã đăng ký, dạng ["METHOD uri" => VersionedRoutes].
     */
    protected array $versionedRoutes = [];

    public function __construct(
        RouteParser $routeParser,
        DataGenerator $dataGenerator,
        protected ?VersionResolver $versionResolver = null
    )
    {
        parent::__construct($routeParser, $dataGenerator);
        $this->versionResolver ??= new VersionResolver();
    }

    public function addRoute($httpMethod, $uri, $route): void
    {
        $fullUri = $this->currentGroupPrefix . $uri;

        foreach ((array) $httpMethod as $method) {
            $method = strtoupper($method);
            $key = "$method $fullUri";

            if (isset($this->versionedRoutes[$key])) {
                // Cùng uri nhưng khác version: gom chung một handler, dispatcher sẽ chọn theo version.
                $this->versionedRoutes[$key]->add($route);
                continue;
            }

            $this->versionedRoutes[$key] = new VersionedRoutes($route);
            parent::addRoute($method, $uri, $this->versionedRoutes[$key]);
        }

        if ($route->hasName()) {
            $this->collection[$route->getName()][$route->getVersion() ?? ''] = $fullUri;
        }
    }

    public function getUrl(string $name, array $params = [], bool $absolute = true, string|int|null $version = null): ?string
    {
        if (isset($this->collection[$name])) {
            $uri = $this->resolveNamedUri($name, $version);
            $uri = preg_replace_callback('/{(\w+)}/', function ($matches) use (&$params) {
                $key = $matches[1];
                if (isset($params[$key])) {
                    $value = $params[$key];
                    unset($params[$key]);
                    return $value;
                }
                return $matches[0];
            }, $uri);

            while (strpos($uri, '[') !== false) {
                $uri = preg_replace_callback('/\[([^\[\]]+)\]/', function ($matches) use (&$params) {
                    $optionalPart = $matches[1];
                    if (preg_match_all('/{(\w+)}/', $optionalPart, $keys)) {
                        foreach ($keys[1] as $key) {
                            if (!isset($params[$key])) {
                                return null;
                            }
                        }
                    }

                    $result = preg_replace_callback('/{(\w+)}/', function ($m) use (&$params) {
                        $key = $m[1];
                        $value = $params[$key];
                        unset($params[$key]);
                        return $value;
                    }, $optionalPart);

                    return $result;
                }, $uri);
            }

            $uri = preg_replace('/{(\w+)}/', '', $uri);

            if (!empty($params)) {
                $queryString = http_build_query($params);
                $uri .= "?$queryString";
            }

            return $absolute ? App::baseUrl() . $uri : $uri;
        }

        return null;
    }

    protected function resolveNamedUri(string $name, string|int|null $version = null): string
    {
        $uris = $this->collection[$name];
        $version = !is_null($version)
            ? (string) $version
            : ($this->versionResolver->getDefaultVersion() ?? '');

        return $uris[$version] ?? $uris[''] ?? reset($uris);
    }

    public function register(string $controller, ?Module $module = null): void
    {
        $controller = new ReflectionClass($controller);
        $groupRouteAttribute = $this->getGroupControllerAttribute($controller);
        if ($groupRouteAttribute?->isDeprecated()) {
            return;
        }

        $prefixModule = $module?->getPrefix() ?? '';
        $prefixClass = !empty($groupRouteAttribute) ? $groupRouteAttribute->getPrefix() : '';
        $prefixName = !empty($groupRouteAttribute) ? $groupRouteAttribute->getName() : '';
        $prefix = $this->joinPath($prefixModule, $prefixClass);

        $actions = $this->collectActions($controller);
        $addRoutes = function (array $actions) use ($controller, $module, $prefixName, $groupRouteAttribute) {
            foreach ($actions as [$method, $routeAttribute, $version]) {
                $route = new \Ryra\Core\Route\Route([
                    'action' => [$controller->getName(), $method->getName()],
                    'name' => $routeAttribute->getName() ? $prefixName . $routeAttribute->getName() : null,
                    'module' => $module,
                    'version' => $version,
                    'middlewares' => array_merge(
                        !empty($groupRouteAttribute) ? $groupRouteAttribute->getMiddlewares() : [],
                        $routeAttribute->getMiddlewares()
                    ),
                    'excluded_middlewares' => array_merge(
                        !empty($groupRouteAttribute) ? $groupRouteAttribute->getExcludedMiddlewares() : [],
                        $routeAttribute->getExcludedMiddlewares()
                    ),
                ]);

                $this->addRoute(
                    $routeAttribute->getMethod(),
                    $routeAttribute->getUri(),
                    $route
                );
            }
        };

        // Chỉ versioning theo uri mới phải tách group vì mỗi version một prefix khác nhau,
        // các kiểu còn lại giữ nguyên thứ tự khai báo của controller.
        if (!$this->versionResolver->isUri()) {
            $this->addGroup($this->versionResolver->applyUriVersion($prefix, null), fn() => $addRoutes($actions));
            return;
        }

        $groups = [];
        foreach ($actions as $action) {
            $groups[$action[2] ?? ''][] = $action;
        }

        foreach ($groups as $versionKey => $actionsOfVersion) {
            $version = $versionKey === '' ? null : (string) $versionKey;
            $this->addGroup(
                $this->versionResolver->applyUriVersion($prefix, $version),
                fn() => $addRoutes($actionsOfVersion)
            );
        }
    }

    /**
     * Lấy các action của controller kèm version cần đăng ký.
     * Version của action ghi đè version của class, không khai báo thì lấy version mặc định.
     *
     * @return array<int, array{0: ReflectionMethod, 1: object, 2: string|null}>
     */
    protected function collectActions(ReflectionClass $controller): array
    {
        $versionsClass = $this->getVersionAttribute($controller);
        $actions = [];

        foreach ($controller->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $routeAttribute = $this->getRouteAttribute($method);
            if (strpos($method->getName(), '__') === 0 || !is_object($routeAttribute)) {
                continue;
            }

            $versions = $this->versionResolver->isEnabled()
                ? ($this->getVersionAttribute($method) ?? $versionsClass ?? $this->versionResolver->getDefaultVersions())
                : [null];

            foreach ($versions as $version) {
                $actions[] = [$method, $routeAttribute, $version];
            }
        }

        return $actions;
    }

    /**
     * @return array<int, string|null>|null
     */
    protected function getVersionAttribute(ReflectionClass|ReflectionMethod $target): ?array
    {
        $attributes = $target->getAttributes(Version::class);
        if (empty($attributes)) {
            return null;
        }

        $versions = $attributes[0]->newInstance()->getVersions();

        return !empty($versions) ? $versions : null;
    }

    protected function getGroupControllerAttribute(ReflectionClass $controller): ?Group
    {
        $attibutes = $controller->getAttributes(Group::class);
        if (!empty($attibutes)) {
            return $attibutes[0]->newInstance();
        }

        return null;
    }

    protected function getRouteAttribute(ReflectionMethod $method): object|null
    {
        $attibutes = $method->getAttributes();
        $allowAttributes = [
            Get::class,
            Post::class,
            Put::class,
            Delete::class,
            Patch::class,
            Head::class,
            Route::class
        ];
        if (!empty($attibutes)) {
            foreach ($attibutes as $attibute) {
                if (in_array($attibute->getName(), $allowAttributes)) {
                    return $attibute->newInstance();
                }
            }
        }
        return null;
    }

    protected function joinPath(string ...$segments): string
    {
        $segments = array_filter($segments);
        if (empty($segments)) {
            return '';
        }

        $result = array_shift($segments);
        foreach ($segments as $segment) {
            $segment = ltrim($segment, '/');
            $result = rtrim($result, '/');
            $result .= '/' . $segment;
        }
        return $result;
    }
}