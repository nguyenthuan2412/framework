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
use Ryra\Core\Context\Context;
use Ryra\Core\Support\Responsable;

class RouteDispatcher extends GroupCountBased
{
    public function __construct(
        RouteCollection $routeCollection,
        protected StackMiddleware $stackMiddleware,
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
                $middlewares = $route->getMiddlewares();
                $action = $route->getAction();
                $controller = App::make($action[0]);
                $method = $action[1];
                $request->setRoute($route);
                $callback = fn ($request) => $controller->{$method}(...$this->getParamMethod(
                    $controller,
                    $method,
                    $request,
                ));
                break;
        }

        $response = $this->stackMiddleware
            ->send($request)
            ->through($middlewares)
            ->then($callback);

        if ($response instanceof Responsable) {
            $response = $response->toResponse();
        }

        if ($response instanceof Response) {
            return $response;
        }
        throw new \UnexpectedValueException('Controller must return a valid Response instance.');
    }

    protected function getParamMethod(object $controller, string $method, Request $request): array
    {
        $routeParams = Context::get('__route.params', []);
        $reflection = new ReflectionMethod($controller, $method);
        $params = $reflection->getParameters();
        $res = [];
        foreach ($params as $param) {
            $typeParam = (string) $param->getType();
            $nameParam = $param->getName();

            if (is_a($typeParam, Request::class, true)) {
                $res[] = $request;
                continue;
            }

            $attributeFindBy = $param->getAttributes(FindBy::class);
            if (!empty($attributeFindBy)) {
                $findBy = $attributeFindBy[0]->newInstance();
                if (
                    !empty($field = (string) $findBy)
                    && isset($routeParams[$field])
                    && is_a($typeParam, Model::class, true)
                ) {
                    $withoutGlobalScopes = $param->getAttributes(WithoutGlobalScopes::class);
                    $query = $typeParam::query();
                    if (!empty($withoutGlobalScopes)) {
                        $withoutGlobalScopes = $withoutGlobalScopes[0]->newInstance();
                        $query = $query->withoutGlobalScopes($withoutGlobalScopes->globalScopes);
                    }

                    $record = $query->where($field, $routeParams[$field])->first();
                    if (empty($record)) {
                        throw new NotFoundHttpException;
                    }
                    $res[] = $record;
                    continue;
                }
            }

            if (isset($routeParams[$nameParam])) {
                if (is_a($typeParam, Model::class, true)) {
                    $query = $typeParam::query();
                    $withoutGlobalScopes = $param->getAttributes(WithoutGlobalScopes::class);
                    if (!empty($withoutGlobalScopes)) {
                        $withoutGlobalScopes = $withoutGlobalScopes[0]->newInstance();
                        $query = $query->withoutGlobalScopes($withoutGlobalScopes->globalScopes);
                    }

                    $res[] = $query->findOrFail($routeParams[$param->getName()]);
                    continue;
                }

                if (enum_exists($typeParam)) {
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

            if (is_a($typeParam, ValidatedDTO::class, true)) {
                $params = $request->all();
                $params = array_merge($params, $routeParams);
                $res[] = new $typeParam($params, true);
            }
        }

        return $res;
    }
}