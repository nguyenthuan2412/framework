# Tạo Controller

## Tổng quan

Controller nhận HTTP request và trả về response. Trong Ryra, controller dùng **PHP 8 attributes** để khai báo route, middleware và thông tin mô tả. Nhờ vậy route được khai báo ngay cạnh code xử lý, không cần file route riêng.

Tạo nhanh controller bằng lệnh:

```bash
php ryra gen:controller User --module=user
```

Lệnh tạo file `modules/user/src/Controllers/UserController.php` (prefix mặc định `api/users`) và tự đăng ký vào `user.module.php`.

---

## 1. `#[Group]`: nhóm route cho cả class

Đặt trên class controller. Khai báo prefix chung và middleware dùng chung cho mọi route trong controller.

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

### Tham số

| Tham số | Kiểu | Mặc định | Mô tả |
|-----------|------|---------|-------------|
| `prefix` | `string` | `''` | Tiền tố URL gắn vào trước mọi route trong controller. Dấu `/` ở đầu và cuối được tự cắt bỏ. |
| `name` | `?string` | `null` | Tên nhóm route (tuỳ chọn), dùng để đặt tên route và sinh URL. |
| `description` | `?string` | `null` | Mô tả nhóm route, dùng khi sinh tài liệu API. |
| `deprecated` | `bool` | `false` | Đánh dấu mọi route trong nhóm là đã lỗi thời (deprecated) trong tài liệu API. |
| `middlewares` | `array` | `[]` | Danh sách middleware áp dụng cho **mọi** route trong controller. |
| `excludedMiddlewares` | `array` | `[]` | Danh sách middleware **bỏ qua** cho các route trong nhóm (hữu ích khi muốn bỏ một middleware toàn cục). |

---

## 2. Attribute theo HTTP method: khai báo route trên từng method

Các attribute `Get`, `Post`, `Put`, `Delete`, `Patch`, `Head` cùng kế thừa một class cha và có chung bộ tham số.

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

### Tham số chung (Get, Post, Put, Delete, Patch, Head)

| Tham số | Kiểu | Mặc định | Mô tả |
|-----------|------|---------|-------------|
| `uri` | `string` | *(bắt buộc)* | Đường dẫn tính từ prefix của group. Hỗ trợ tham số dạng `{param}` và `{param:regex}`. Dùng `'/'` hoặc `''` cho chính đường dẫn prefix. |
| `name` | `?string` | `null` | Tên route, dùng để sinh URL từ tên route. |
| `summary` | `?string` | `null` | Tóm tắt ngắn về endpoint, hiển thị trong tài liệu API (Swagger/OpenAPI). |
| `description` | `?string` | `null` | Mô tả chi tiết endpoint, hiển thị trong tài liệu API. |
| `deprecated` | `bool` | `false` | Đánh dấu endpoint đã lỗi thời trong tài liệu API. |
| `middlewares` | `array` | `[]` | Middleware áp dụng **chỉ** cho route này. Được gộp với middleware của group. |
| `excludedMiddlewares` | `array` | `[]` | Middleware bỏ qua cho route này (ghi đè middleware của group hoặc toàn cục). |

---

## 3. `#[Route]`: một route nhận nhiều HTTP method

Khi một endpoint cần nhận **nhiều HTTP method**, dùng `#[Route]` thay cho các attribute riêng lẻ.

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

### Tham số

| Tham số | Kiểu | Mặc định | Mô tả |
|-----------|------|---------|-------------|
| `methods` | `string\|array` | *(bắt buộc)* | Một hoặc nhiều HTTP method mà route nhận. Có thể là chuỗi `'GET'` hoặc mảng `['GET', 'POST']`. |
| `uri` | `string` | *(bắt buộc)* | Đường dẫn tính từ prefix của group, cú pháp giống các attribute khác. |
| `name` | `?string` | `null` | Tên route. |
| `summary` | `?string` | `null` | Tóm tắt ngắn cho tài liệu API. |
| `description` | `?string` | `null` | Mô tả chi tiết cho tài liệu API. |
| `deprecated` | `bool` | `false` | Đánh dấu endpoint đã lỗi thời. |
| `middlewares` | `array` | `[]` | Middleware cho route này. |
| `excludedMiddlewares` | `array` | `[]` | Middleware bỏ qua cho route này. |

---

## 4. Ví dụ đầy đủ

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

## 5. Ghi nhớ

- Khai báo route bằng **PHP 8 attributes**: `#[Get]`, `#[Post]`, `#[Put]`, `#[Delete]`, `#[Patch]`, `#[Head]`, `#[Route]`
- Nhóm route bằng `#[Group(prefix: '...', middlewares: [...])]`
- URI hỗ trợ tham số `{param}` và `{param:regex}` (ví dụ `{id:\d+}`)
- Inject service qua constructor
- Trả JSON bằng `Response::json()`, trả HTML bằng `Response::view()`
- DTO khai báo trong tham số method được tự validate từ body/query của request
- Tự nạp model từ tham số route (ví dụ `Customer $customer` với route `{customer}`)
- Middleware được gộp theo thứ tự: group → method, sau đó lọc bỏ theo `excludedMiddlewares`
- Các thông tin `summary`, `description`, `deprecated` được tự đọc để sinh tài liệu Swagger/OpenAPI
