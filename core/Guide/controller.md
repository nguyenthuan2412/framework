# Create a Controller

## Overview

Controllers handle incoming HTTP requests and return responses. In Ryra, controllers use **PHP 8 attributes** to define routes, middleware, and metadata — keeping routing declarative and close to the handler logic.

---

## 1. `#[Group]` — Class-level route group

Applied to the controller class. Defines a common prefix and shared middleware for all routes inside the controller.

```php
use Ryra\Core\Route\Attributes\Group;

#[Group(
    prefix: 'api/resource',
    name: 'resource',
    description: 'Resource management endpoints',
    deprecated: false,
    middlewares: [AuthMiddleware::class],
    excludedMiddlewares: [LogMiddleware::class]
)]
class SomeController
{
    // ...
}
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `prefix` | `string` | `''` | URL prefix prepended to every route URI in the controller. Leading/trailing slashes are trimmed automatically. |
| `name` | `?string` | `null` | Optional route group name, useful for route naming and URL generation. |
| `description` | `?string` | `null` | Description of the route group, used in API documentation generation. |
| `deprecated` | `bool` | `false` | Marks all routes in the group as deprecated in API docs. |
| `middlewares` | `array` | `[]` | List of middleware classes applied to **every** route in this controller. |
| `excludedMiddlewares` | `array` | `[]` | List of middleware classes to **exclude** from routes in this group (useful when a global middleware should be skipped). |

---

## 2. HTTP Method Attributes — Method-level route definition

All HTTP method attributes (`Get`, `Post`, `Put`, `Delete`, `Patch`, `Head`) extend the same base class and share the same parameters.

### `#[Get]`

```php
use Ryra\Core\Route\Attributes\Get;

#[Get(
    uri: '{id:\d+}',
    name: 'resource.show',
    summary: 'Get resource by ID',
    description: 'Returns the full resource object for the given ID',
    deprecated: false,
    middlewares: [RateLimitMiddleware::class],
    excludedMiddlewares: [],
)]
public function show(int $id): JsonResponse
```

### `#[Post]`

```php
use Ryra\Core\Route\Attributes\Post;

#[Post(
    uri: '/',
    name: 'resource.store',
    summary: 'Create a new resource',
    description: 'Creates a resource from the provided payload',
    middlewares: [ValidationMiddleware::class],
)]
public function store(StoreDTO $dto): JsonResponse
```

### `#[Put]`

```php
use Ryra\Core\Route\Attributes\Put;

#[Put(
    uri: '{id:\d+}',
    name: 'resource.update',
    summary: 'Update an existing resource',
    description: 'Fully replaces the resource with the provided payload',
)]
public function update(int $id, UpdateDTO $dto): JsonResponse
```

### `#[Delete]`

```php
use Ryra\Core\Route\Attributes\Delete;

#[Delete(
    uri: '{id:\d+}',
    name: 'resource.destroy',
    summary: 'Delete a resource',
    description: 'Permanently removes the resource from the system',
)]
public function destroy(int $id): JsonResponse
```

### `#[Patch]`

```php
use Ryra\Core\Route\Attributes\Patch;

#[Patch(
    uri: '{id:\d+}',
    name: 'resource.partial-update',
    summary: 'Partially update a resource',
    description: 'Updates only the provided fields of the resource',
)]
public function partialUpdate(int $id, UpdateDTO $dto): JsonResponse
```

### `#[Head]`

```php
use Ryra\Core\Route\Attributes\Head;

#[Head(
    uri: '{id:\d+}',
    name: 'resource.head',
    summary: 'Get response headers for resource',
    description: 'Same as GET but returns only headers, no body'
)]
public function head(int $id): JsonResponse
```

### Shared Parameters (Get, Post, Put, Delete, Patch, Head)

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `uri` | `string` | *(required)* | URI path relative to the group prefix. Supports `{param}` and `{param:regex}` patterns for route parameters. Use `'/'` or `''` for the root of the group prefix. |
| `name` | `?string` | `null` | Route name for named route generation and reverse routing. |
| `summary` | `?string` | `null` | Short summary of the endpoint, used in API documentation (e.g., Swagger/OpenAPI). |
| `description` | `?string` | `null` | Detailed description of the endpoint's behavior, used in API docs. |
| `deprecated` | `bool` | `false` | Marks the endpoint as deprecated in API docs. |
| `middlewares` | `array` | `[]` | Middleware classes applied **only** to this specific route. Merged with group-level middleware. |
| `excludedMiddlewares` | `array` | `[]` | Middleware classes to exclude from this specific route (overrides group/applied middleware). |

---

## 3. `#[Route]` — Custom HTTP methods attribute

When you need to handle **multiple HTTP methods** on a single endpoint, use `#[Route]` instead of the specific method attributes.

```php
use Ryra\Core\Route\Attributes\Route;

#[Route(
    methods: ['GET', 'POST'],
    uri: '{id:\d+}/options',
    name: 'resource.options',
    summary: 'Get or update resource options',
    description: 'GET returns options, POST updates them',
    deprecated: false,
    middlewares: [AuthMiddleware::class],
    excludedMiddlewares: [],
)]
public function options(int $id): JsonResponse
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `methods` | `string\|array` | *(required)* | One or more HTTP methods this route responds to. Can be a string like `'GET'` or an array like `['GET', 'POST']`. |
| `uri` | `string` | *(required)* | URI path relative to the group prefix. Same pattern support as other attributes. |
| `name` | `?string` | `null` | Route name for named route generation. |
| `summary` | `?string` | `null` | Short summary for API documentation. |
| `description` | `?string` | `null` | Detailed description for API documentation. |
| `deprecated` | `bool` | `false` | Marks the endpoint as deprecated. |
| `middlewares` | `array` | `[]` | Middleware classes for this route. |
| `excludedMiddlewares` | `array` | `[]` | Middleware classes to exclude from this route. |

---

## 4. Complete Example

```php
<?php

namespace Ryra\Modules\{ModuleName}\Controllers;

use Ryra\Core\Route\Attributes\Group;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Route\Attributes\Post;
use Ryra\Core\Route\Attributes\Put;
use Ryra\Core\Route\Attributes\Delete;
use Ryra\Core\Route\Attributes\Patch;
use Ryra\Core\Route\Attributes\Head;
use Ryra\Core\Route\Attributes\Route;
use Ryra\Core\Http\Response;
use Ryra\Core\Http\JsonResponse;

#[Group(
    prefix: 'api/resources',
    name: 'resources',
    description: 'Resource management',
    middlewares: [AuthMiddleware::class],
    excludedMiddlewares: []
)]
class ResourceController
{
    #[Get(uri: '/', summary: 'List all resources')]
    public function index(SearchDTO $dto): JsonResponse
    {
        return Response::json(['data' => $result]);
    }

    #[Post(uri: '/', summary: 'Create a resource')]
    public function store(StoreDTO $request): JsonResponse
    {
        return Response::json(['message' => 'Created'], 201);
    }

    #[Get(uri: '{id:\d+}', summary: 'Get resource by ID')]
    public function show(int $id): JsonResponse
    {
        return Response::json(['data' => $resource]);
    }

    #[Put(uri: '{id:\d+}', summary: 'Replace resource')]
    public function update(int $id, UpdateDTO $request): JsonResponse
    {
        return Response::json(['message' => 'Updated']);
    }

    #[Delete(uri: '{id:\d+}', summary: 'Delete resource')]
    public function destroy(int $id): JsonResponse
    {
        return Response::json(['message' => 'Deleted']);
    }

    #[Patch(uri: '{id:\d+}', summary: 'Partially update resource')]
    public function partialUpdate(int $id, UpdateDTO $request): JsonResponse
    {
        return Response::json(['message' => 'Patched']);
    }

    #[Head(uri: '{id:\d+}', summary: 'Get resource headers only')]
    public function head(int $id): JsonResponse
    {
        return Response::json(['headers' => $headers]);
    }

    #[Route(methods: ['GET', 'POST'], uri: '{id:\d+}/options', summary: 'Manage resource options')]
    public function options(int $id): JsonResponse
    {
        return Response::json(['options' => $options]);
    }
}
```

---

## 5. Key Points

- Use **PHP 8 attributes** for routing: `#[Get]`, `#[Post]`, `#[Put]`, `#[Delete]`, `#[Patch]`, `#[Head]`, `#[Route]`
- Group routes with `#[Group(prefix: '...', middlewares: [...])]`
- URI supports `{param}` and `{param:regex}` patterns (e.g., `{id:\d+}`)
- Constructor injection for service interfaces
- Return `JsonResponse` via `Response::json()`
- DTOs are auto-validated from request body/query
- Model binding in route params (e.g., `Customer $customer`)
- Middleware stacks merge: group-level → method-level, with `excludedMiddlewares` filtering
- API documentation metadata (`summary`, `description`, `deprecated`) is auto-extracted for Swagger/OpenAPI generation
