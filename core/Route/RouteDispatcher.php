<?php

namespace Ryra\Core\Route;

use Ryra\Core\Http\Exception\MethodNotAllowedHttpException;
use Ryra\Core\Database\Attributes\WithoutGlobalScopes;
use Ryra\Core\Http\Exception\NotFoundHttpException;
use Ryra\Core\Middleware\MiddlewareCollection;
use Ryra\Core\Middleware\StackMiddleware;
use Ryra\Core\Database\Attributes\FindBy;
use Ryra\Core\ValidatedDTO\ValidatedDTO;
use FastRoute\Dispatcher\GroupCountBased;
use Ryra\Core\Route\Versioning\VersionedRoutes;
use Ryra\Core\Route\Versioning\VersionResolver;
use Ryra\Core\Database\Model\Model;
use Ryra\Core\Http\Response;
use Ryra\Core\Http\Request;
use Ryra\Core\App;
use ReflectionMethod;
use Ryra\Core\Container\AOP\AopProxy;
use Ryra\Core\Context\Context;
use Ryra\Core\Support\Responsable;

class RouteDispatcher extends GroupCountBased
{
    /**
     * Chuỗi middleware + action đã dựng sẵn cho từng route (theo spl_object_id của Route).
     * Dựng một lần mỗi worker thay vì mỗi request phải make() từng middleware, tạo SplStack
     * và lồng lại closure. Chuỗi không giữ state của request nên dùng chung giữa các coroutine.
     *
     * @var array<int, \Closure(Request): mixed>
     */
    protected array $routeChains = [];

    /**
     * Instance middleware dùng chung trong worker (giống Express/Fastify/Hyperf):
     * middleware HTTP phải stateless, không lưu dữ liệu của request vào property.
     *
     * @var array<string, object>
     */
    protected array $middlewareInstances = [];
    public function __construct(
        RouteCollection $routeCollection,
        protected MiddlewareCollection $middlewareCollection,
        protected ?VersionResolver $versionResolver = null
    )
    {
        parent::__construct($routeCollection->getData());
        $this->versionResolver ??= new VersionResolver();
    }

    public function handle(Request $request): Response
    {
        $routeInfo = $this->dispatch($request->method(), $request->uri());
        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                $callback = static fn () => throw new NotFoundHttpException();
                $middlewares = $this->middlewareCollection->all();
                break;
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                $callback = static fn () => throw new MethodNotAllowedHttpException($allowedMethods);
                $middlewares = $this->middlewareCollection->all();
                break;
            default:
                $route = $routeInfo[1] instanceof VersionedRoutes
                    ? $routeInfo[1]->match($this->versionResolver->resolve($request))
                    : $routeInfo[1];

                if (is_null($route)) {
                    // Uri khớp nhưng không có version nào phục vụ được request.
                    $callback = static fn () => throw new NotFoundHttpException();
                    $middlewares = $this->middlewareCollection->all();
                    break;
                }

                Context::put('__route.params', $routeInfo[2]);
                Context::put('__route.version', $route->getVersion());
                $request->setRoute($route);
                $response = ($this->routeChains[\spl_object_id($route)] ??= $this->buildRouteChain($route))($request);
                break;
        }

        if (isset($callback)) {
            // 404/405: hiếm gặp, giữ nguyên pipeline cũ với middleware toàn cục.
            $response = (new StackMiddleware)
                ->send($request)
                ->through($middlewares)
                ->then($callback);
        }

        if ($response instanceof Responsable) {
            $response = $response->toResponse();
        }

        if ($response instanceof Response) {
            return $response;
        }
        throw new \UnexpectedValueException('Controller must return a valid Response instance.');
    }

    /**
     * Dựng chuỗi xử lý của route: middleware (ngoài -> trong theo thứ tự khai báo) rồi tới action.
     * Controller vẫn được tạo mới cho mỗi request (ngay trước khi gọi action).
     */
    protected function buildRouteChain(Route $route): \Closure
    {
        [$class, $method] = $route->getAction();
        $next = function (Request $request) use ($class, $method): mixed {
            $controller = App::make($class);
            return $controller->{$method}(...$this->getParamMethod($controller, $method, $request));
        };

        foreach (\array_reverse($route->getMiddlewares()) as $middleware) {
            $instance = \is_string($middleware)
                ? ($this->middlewareInstances[$middleware] ??= App::make($middleware))
                : $middleware;
            $next = static fn(Request $request): mixed => $instance->handle($request, $next);
        }

        return $next;
    }

    /**
     * Chuẩn bị sẵn mọi thứ cần Reflection/IO cho các route: proxy (factory + AOP) của controller,
     * middleware và phân tích tham số action. Gọi lúc worker khởi động để request đầu tiên không
     * phải làm việc này (và không bị nhiều coroutine cùng làm một lúc khi worker vừa lên đã có tải).
     */
    public function warmUp(RouteCollection $routes): void
    {
        $classes = [];
        foreach ($routes->getRoutes() as $route) {
            $action = $route->getAction();
            if (\is_array($action) && \is_string($action[0] ?? null) && \class_exists($action[0])) {
                $classes[$action[0]] = true;
                if (\is_string($action[1] ?? null) && \method_exists($action[0], $action[1])) {
                    static::$actionParams[$action[0] . '::' . $action[1]] ??= static::analyzeAction($action[0], $action[1]);
                }
            }
            foreach ($route->getMiddlewares() as $middleware) {
                if (\is_string($middleware) && \class_exists($middleware)) {
                    $classes[$middleware] = true;
                }
            }
        }

        foreach (\array_keys($classes) as $class) {
            App::warmUp($class);
        }

        foreach ($routes->getRoutes() as $route) {
            $action = $route->getAction();
            if (\is_array($action) && isset($classes[$action[0] ?? ''])) {
                $this->routeChains[\spl_object_id($route)] ??= $this->buildRouteChain($route);
            }
        }
    }

    /**
     * Mô tả tham số của action đã phân tích, theo "Controller::method".
     * Reflection chỉ chạy lần đầu gặp action trong mỗi worker, các request sau chỉ tra mảng.
     *
     * @var array<string, list<array{
     *     name: string, type: string, request: bool, model: bool, enum: bool, dto: bool,
     *     findBy: ?string, withoutGlobalScopes: ?array
     * }>>
     */
    protected static array $actionParams = [];

    protected function getParamMethod(object $controller, string $method, Request $request): array
    {
        $routeParams = Context::get('__route.params', []);
        // Controller có thể là class AOP sinh tự động: phân tích theo class gốc.
        $class = $controller instanceof AopProxy ? $controller::AOP_TARGET : $controller::class;
        $params = static::$actionParams[$class . '::' . $method] ??= static::analyzeAction($class, $method);

        $res = [];
        foreach ($params as $param) {
            if ($param['request']) {
                $res[] = $request;
                continue;
            }

            $typeParam = $param['type'];
            if ($param['findBy'] !== null && isset($routeParams[$field = $param['findBy']])) {
                $query = $typeParam::query();
                if ($param['withoutGlobalScopes'] !== null) {
                    $query = $query->withoutGlobalScopes($param['withoutGlobalScopes']);
                }

                $record = $query->where($field, $routeParams[$field])->first();
                if (empty($record)) {
                    throw new NotFoundHttpException;
                }
                $res[] = $record;
                continue;
            }

            if (isset($routeParams[$nameParam = $param['name']])) {
                if ($param['model']) {
                    $query = $typeParam::query();
                    if ($param['withoutGlobalScopes'] !== null) {
                        $query = $query->withoutGlobalScopes($param['withoutGlobalScopes']);
                    }

                    $res[] = $query->findOrFail($routeParams[$nameParam]);
                    continue;
                }

                if ($param['enum']) {
                    try {
                        $val = $typeParam::tryFrom($routeParams[$nameParam]);
                    } catch (\Throwable $th) {
                        $val = null;
                    }
                    if (is_null($val)) {
                        throw new NotFoundHttpException;
                    }

                    $res[] = $val;
                    continue;
                }

                $res[] = $routeParams[$nameParam];
                continue;
            }

            if ($param['dto']) {
                $res[] = new $typeParam(array_merge($request->all(), $routeParams), true);
            }
        }

        return $res;
    }

    /**
     * Phân tích tham số của action một lần (chỉ đọc metadata tĩnh: type, attribute).
     */
    protected static function analyzeAction(string $class, string $method): array
    {
        $result = [];
        foreach ((new ReflectionMethod($class, $method))->getParameters() as $param) {
            $type = (string) $param->getType();
            $isModel = is_a($type, Model::class, true);

            $findBy = null;
            if ($isModel && !empty($attribute = $param->getAttributes(FindBy::class))) {
                $field = (string) $attribute[0]->newInstance();
                $findBy = $field !== '' ? $field : null;
            }

            $withoutGlobalScopes = null;
            if (!empty($attribute = $param->getAttributes(WithoutGlobalScopes::class))) {
                $withoutGlobalScopes = $attribute[0]->newInstance()->globalScopes;
            }

            $result[] = [
                'name' => $param->getName(),
                'type' => $type,
                'request' => is_a($type, Request::class, true),
                'model' => $isModel,
                'enum' => enum_exists($type),
                'dto' => is_a($type, ValidatedDTO::class, true),
                'findBy' => $findBy,
                'withoutGlobalScopes' => $withoutGlobalScopes,
            ];
        }

        return $result;
    }
}
