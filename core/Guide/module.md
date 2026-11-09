# Tạo Module

Cách nhanh nhất để tạo module mới là dùng lệnh có sẵn:

```bash
php ryra gen:module {ten_module}
```

Ví dụ tạo module `user`:

```bash
php ryra gen:module user
```

Lệnh này tự tạo cấu trúc thư mục, file khai báo module (manifest) và service provider.

Bạn cũng có thể tạo module thủ công theo các bước dưới đây.

---

## Bước 1: Tạo cấu trúc thư mục
```
modules/{ten_module}/
├── {ten_module}.module.php
├── src/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── DTOs/
│   ├── Enums/
│   ├── Providers/
│   ├── Middlewares/
│   └── Repositories/
└── views/
```

## Bước 2: Tạo file khai báo module
Tạo file `modules/{ten_module}/{ten_module}.module.php`:

```php
<?php

return [
    'name' => 'Module Name',
    'controllers' => [
        // Đăng ký các class controller tại đây
    ],
    'providers' => [
        // Đăng ký các service provider tại đây
    ],
];
```

## Bước 3: Các khoá trong file khai báo module

| Khoá | Bắt buộc | Mô tả |
|------|----------|-------|
| `name` | Có | Tên hiển thị của module |
| `description` | | Mô tả module |
| `prefix` | | Tiền tố URL cho mọi route trong module, gắn trước prefix của `#[Group]` |
| `controllers` | | Danh sách controller HTTP. Route được đọc từ attribute trong các class này |
| `providers` | | Danh sách service provider, chạy theo đúng thứ tự khai báo |
| `middlewares` | | Middleware áp dụng cho mọi route của module (xem [middleware.md](middleware.md)) |
| `commands` | | Lệnh console của module (class Symfony Command có `#[AsCommand]`) |
| `websocket_controllers` | | Controller WebSocket |

Ví dụ đầy đủ:

```php
<?php

use Ryra\Modules\Book\Controllers\BookController;
use Ryra\Modules\Book\Providers\BookServiceProvider;
use Ryra\Modules\Book\Commands\SyncBooksCommand;
use Ryra\Core\Session\Middleware\StartSession;

return [
    'name' => 'Book',
    'description' => 'Quản lý sách',
    'prefix' => '',

    'controllers' => [
        BookController::class,
    ],

    'providers' => [
        BookServiceProvider::class,
    ],

    'middlewares' => [
        StartSession::class,
    ],

    'commands' => [
        SyncBooksCommand::class,
    ],
];
```

## Ghi nhớ
- Module được nạp tự động. Chỉ cần thư mục nằm trong `modules/` và có file `{ten_module}.module.php`
- Namespace của module là `Ryra\Modules\{TenModule}`, ánh xạ tới `modules/{ten_module}/src/`
- View Twig trong `views/` được gọi bằng `Response::view('file.twig')` từ controller của chính module đó, hoặc `@{ten_module}/file.twig` từ nơi khác
- Các lệnh `gen:controller` và `gen:provider` tự thêm class vào file khai báo module
