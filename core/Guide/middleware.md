# Create Middleware

## Generate middleware via command

You can quickly scaffold a middleware class using the built-in generator:

```bash
php ryra gen:middleware --module=user EnsureTokenIsValid
```

This creates a middleware class at `modules/user/src/Middlewares/EnsureTokenIsValid.php` with the basic structure ready for you to implement.

The command accepts the following options:

| Option | Description |
|--------|-------------|
| `--module` | The module the middleware belongs to (required) |
| `-f` / `--force` | Overwrite the file if it already exists |

The generator automatically strips the `Middleware` suffix if you include it, so both `EnsureTokenIsValid` and `EnsureTokenIsValidMiddleware` produce the same file.

## Basic structure

```php
<?php

namespace Ryra\Modules\{ModuleName}\Middlewares;

use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;
use Ryra\Core\Middleware\MiddlewareInterface;

class EnsureTokenIsValid implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Logic before controller execution
        // e.g., check permissions, modify request, etc.

        // Continue to next middleware / controller
        $response = $next($request);

        // Logic after controller execution
        // e.g., modify response, add headers, etc.

        return $response;
    }
}
```

## Usage

Middleware can be registered at **four different levels**, depending on how broadly you want it to apply:

### 1. Global — `ryra` file

Register middleware in the [`ryra`](/ryra:23) entry file via `withMiddleware()` to apply it to **every request** across the entire application:

```php
// ryra
->withMiddleware(static function (MiddlewareCollection $middlewares): void {
    $middlewares->use([
        HandleCors::class,
        HandleException::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        EnsureTokenIsValid::class,
    ]);
})
```

### 2. Module-wide — `.module.php` file

Add a `middlewares` key in the module's `.module.php` config file to apply middleware to **all routes** within that module:

```php
// modules/user/user.module.php
return [
    'name' => 'User Management',

    'controllers' => [
        UserController::class,
    ],

    'providers' => [
        UserServiceProvider::class,
    ],

    'middlewares' => [
        EnsureTokenIsValid::class,
    ],
];
```

The module manager picks up this key and registers each middleware to the module via [`MiddlewareServiceProvider`](/core/Middleware/MiddlewareServiceProvider.php:22).

### 3. Group — `#[Group]` attribute

Attach middleware to a group of routes via the [`#[Group]`](/core/Route/Attributes/Group.php:12) attribute on a controller class:

```php
use Ryra\Core\Route\Attributes\Group;

#[Group(prefix: 'api/admin', middlewares: [AuthMiddleware::class, EnsureTokenIsValid::class])]
class AdminController
{
    // ...
}
```

### 4. Single route — `#[Route]` attribute

Attach middleware to an individual route via the [`#[Route]`](/core/Route/Attributes/Route.php:12) attribute on a controller method:

```php
use Ryra\Core\Route\Attributes\Route;

#[Route(methods: ['POST'], uri: '/login', middlewares: [EnsureTokenIsValid::class])]
public function login(Request $request): Response
{
    // ...
}
```

### Excluding middleware

Both `#[Group]` and `#[Route]` support an `excludedMiddlewares` parameter to exclude specific middleware at a more granular level:

```php
#[Group(prefix: 'api/admin', middlewares: [AuthMiddleware::class, LogRequest::class], excludedMiddlewares: [LogRequest::class])]
class PublicController
{
    // ...
}
```

## Execution order

Middleware runs in the following order (outermost first):

1. **Global** — registered in `ryra` via `withMiddleware()`
2. **Module** — registered in `.module.php` under the `middlewares` key
3. **Group** — registered via `#[Group(middlewares: [...])]`
4. **Route** — registered via `#[Route(middlewares: [...])]`

Each level passes the request to the next via `$next($request)`, so the **Route-level middleware runs closest to the controller**.

## Key points

- Implements [`MiddlewareInterface`](/core/Middleware/MiddlewareInterface.php)
- `$next($request)` passes control to the next middleware or controller
- Can modify request before and response after
- Multiple middlewares can be stacked at each level
- Use `excludedMiddlewares` to opt out of inherited middleware at group or route level
