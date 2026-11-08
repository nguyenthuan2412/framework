# Tạo Middleware

## Sinh middleware bằng lệnh

Bạn có thể tạo nhanh một class middleware bằng lệnh có sẵn:

```bash
php ryra gen:middleware --module=user EnsureTokenIsValid
```

Lệnh này tạo file `modules/user/src/Middlewares/EnsureTokenIsValidMiddleware.php` (class `EnsureTokenIsValidMiddleware`) với cấu trúc cơ bản, bạn chỉ cần viết logic.

Các tuỳ chọn của lệnh:

| Tuỳ chọn | Mô tả |
|----------|-------|
| `--module` | Module chứa middleware (bắt buộc) |
| `-f` / `--force` | Ghi đè nếu file đã tồn tại |

Lệnh luôn thêm hậu tố `Middleware` vào tên class. Nếu bạn đã gõ sẵn hậu tố thì lệnh không thêm lần nữa, nên `EnsureTokenIsValid` và `EnsureTokenIsValidMiddleware` đều tạo ra cùng một file.

> Middleware tạo bằng lệnh **không** được tự đăng ký vào đâu cả. Bạn tự gắn nó theo một trong bốn cách ở mục "Cách sử dụng".

## Cấu trúc cơ bản

```php
<?php

namespace Ryra\Modules\{ModuleName}\Middlewares;

use Closure;
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;

class EnsureTokenIsValidMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Xử lý trước khi vào controller
        // ví dụ: kiểm tra quyền, chỉnh sửa request, ...

        // Chuyển sang middleware tiếp theo / controller
        $response = $next($request);

        // Xử lý sau khi controller chạy xong
        // ví dụ: sửa response, thêm header, ...

        return $response;
    }
}
```

## Cách sử dụng

Middleware có thể đăng ký ở **bốn cấp độ**, tuỳ phạm vi bạn muốn áp dụng:

### 1. Toàn cục — file `ryra`

Đăng ký trong file chạy chính [`ryra`](../../ryra) qua `withMiddleware()` để áp dụng cho **mọi request** của ứng dụng:

```php
// ryra
->withMiddleware(static function (MiddlewareCollection $middlewares): void {
    $middlewares->use([
        HandleCors::class,
        HandleException::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
        EnsureTokenIsValidMiddleware::class,
    ]);
})
```

### 2. Toàn module — file `.module.php`

Thêm khoá `middlewares` vào file `.module.php` của module để áp dụng cho **mọi route** trong module đó:

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
        EnsureTokenIsValidMiddleware::class,
    ],
];
```

Module manager đọc khoá này và đăng ký từng middleware cho module qua [`MiddlewareServiceProvider`](../Middleware/MiddlewareServiceProvider.php#L22).

### 3. Nhóm route — attribute `#[Group]`

Gắn middleware cho cả nhóm route bằng attribute [`#[Group]`](../Route/Attributes/Group.php#L12) đặt trên class controller:

```php
use Ryra\Core\Route\Attributes\Group;

#[Group(prefix: 'api/admin', middlewares: [AuthMiddleware::class, EnsureTokenIsValidMiddleware::class])]
class AdminController
{
    // ...
}
```

### 4. Một route — attribute `#[Route]`

Gắn middleware cho riêng một route bằng attribute [`#[Route]`](../Route/Attributes/Route.php#L12) (hoặc `#[Get]`, `#[Post]`, ...) đặt trên method của controller:

```php
use Ryra\Core\Route\Attributes\Route;

#[Route(methods: ['POST'], uri: '/login', middlewares: [EnsureTokenIsValidMiddleware::class])]
public function login(Request $request): Response
{
    // ...
}
```

### Loại trừ middleware

Cả `#[Group]` và `#[Route]` đều có tham số `excludedMiddlewares` để bỏ bớt middleware ở cấp chi tiết hơn:

```php
#[Group(prefix: 'api/admin', middlewares: [AuthMiddleware::class, LogRequest::class], excludedMiddlewares: [LogRequest::class])]
class PublicController
{
    // ...
}
```

## Thứ tự thực thi

Middleware chạy theo thứ tự sau (lớp ngoài cùng chạy trước):

1. **Toàn cục**: đăng ký trong `ryra` qua `withMiddleware()`
2. **Module**: đăng ký trong `.module.php`, khoá `middlewares`
3. **Nhóm**: đăng ký qua `#[Group(middlewares: [...])]`
4. **Route**: đăng ký qua `#[Route(middlewares: [...])]`

Mỗi cấp chuyển request cho cấp tiếp theo qua `$next($request)`, nên **middleware cấp route chạy gần controller nhất**.

## Ghi nhớ

- Là class thường (không cần implement interface), có method `handle(Request $request, Closure $next)`. Xem ví dụ thật trong [core/Middleware/](../Middleware/)
- `$next($request)` chuyển quyền xử lý cho middleware tiếp theo hoặc controller
- Có thể sửa request trước khi chuyển đi và sửa response sau khi nhận về
- Mỗi cấp có thể khai báo nhiều middleware
- Dùng `excludedMiddlewares` để bỏ middleware được kế thừa ở cấp group hoặc route
