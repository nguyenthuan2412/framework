# Ryra Framework

Ryra là một PHP framework chạy trên **Swoole** (server chạy liên tục, hỗ trợ coroutine), tổ chức code theo **module**. Route, middleware và validate được khai báo bằng **PHP 8 attributes**.

Framework có sẵn: HTTP/WebSocket server, Socket.IO, ORM + Query Builder, ValidatedDTO, Auth/JWT, Cache/Redis, Queue (Messenger), Mail, Twig view, Excel, PDF, xử lý ảnh, sinh API docs (OpenAPI), tích hợp AI (OpenAI / Claude / Gemini), v.v.

---

## Mục lục

1. [Yêu cầu hệ thống](#1-yêu-cầu-hệ-thống)
2. [Cài đặt](#2-cài-đặt)
3. [Cấu hình `.env`](#3-cấu-hình-env)
4. [Chạy ứng dụng](#4-chạy-ứng-dụng)
5. [Cấu trúc thư mục](#5-cấu-trúc-thư-mục)
6. [Bắt đầu nhanh: tạo module đầu tiên](#6-bắt-đầu-nhanh-tạo-module-đầu-tiên)
7. [Các lệnh CLI](#7-các-lệnh-cli)
8. [Tài liệu chi tiết](#8-tài-liệu-chi-tiết)
9. [Lưu ý khi dùng Swoole](#9-lưu-ý-khi-dùng-swoole)

---

## 1. Yêu cầu hệ thống

- PHP **8.3+**
- Extension: `swoole` (5.1+), `mbstring`, `ffi`, `intl`, `zip`, `pdo_mysql`, `redis` (nếu dùng Redis)
- Composer 2
- MySQL / MariaDB (nếu dùng database)
- Redis (tuỳ chọn: cache, session, queue)

> Cách dễ nhất là dùng Docker (mục 2.2), image đã cài sẵn các extension cần thiết.

## 2. Cài đặt

### 2.1. Chạy trực tiếp trên máy

```bash
git clone <repo-url> my-app
cd my-app
composer install
cp .env.example .env   # nếu chưa có file mẫu thì tự tạo .env theo mục 3
```

### 2.2. Chạy bằng Docker

Tạo file `.env` ở thư mục gốc, có ít nhất 2 biến để build image:

```dotenv
PHP_VERSION=8.3
COMPOSER_VERSION=2
```

Sau đó:

```bash
docker compose up -d --build
docker exec -it tourdb-php bash      # vào container
composer install
php ryra start:serve
```

Container mở cổng `9090`, nên đặt `APP_PORT=9090` trong `.env`.

## 3. Cấu hình `.env`

Các giá trị cấu hình nằm trong thư mục [config/](config/) và đọc từ `.env` qua hàm `env()`. File `.env` mẫu tối thiểu:

```dotenv
APP_NAME=Ryra
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:9090
APP_HOST=0.0.0.0
APP_PORT=9090
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_LOCALE=vi
APP_FALLBACK_LOCALE=en
APP_KEY=                       # chuỗi ngẫu nhiên, dùng để mã hoá session/cookie

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ryra
DB_USERNAME=root
DB_PASSWORD=

# Redis (tuỳ chọn)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Cache / Session
CACHE_STORE=database           # hoặc redis
SESSION_DRIVER=file

# Mail (tuỳ chọn)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=

# AI (tuỳ chọn)
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
```

Các tích hợp khác (S3/MinIO, RabbitMQ, Elasticsearch, ...) có biến riêng, xem file tương ứng trong [config/](config/).

**Chọn loại server** trong [config/app.php](config/app.php), mục `swoole.default`:
- `websocket` (mặc định): HTTP + WebSocket
- `http`: chỉ HTTP

## 4. Chạy ứng dụng

```bash
# Khởi động server
php ryra start:serve

# Chế độ dev: tự reload khi sửa file
php ryra start:serve --watch    # hoặc -W
```

Mở trình duyệt tại `http://localhost:<APP_PORT>/example` để xem module mẫu.

- Log của Swoole: `storage/vietiso/logs/swoole.log`
- File tĩnh: đặt trong `storage/public/`, truy cập qua `/public/...`

## 5. Cấu trúc thư mục

```
.
├── ryra                  # File chạy chính (CLI + khai báo middleware toàn cục)
├── config/               # File cấu hình (app, database, cache, mail, ...)
├── core/                 # Mã nguồn framework (namespace Ryra\Core)
│   ├── Guide/            # Tài liệu hướng dẫn chi tiết (xem mục 8)
│   ├── providers.php     # Danh sách service provider của core
│   └── ...
├── modules/              # Code ứng dụng, mỗi tính năng là một module
│   └── example/          # Module mẫu
├── lang/                 # File dịch đa ngôn ngữ
├── storage/              # Cache, log, file public
└── docker/               # Dockerfile và cấu hình môi trường
```

### Quy tắc module

Mỗi module nằm trong `modules/{ten_module}/` và có namespace `Ryra\Modules\{TenModule}\...`. Code đặt trong `src/`; autoload tự ánh xạ namespace sang thư mục (xem [core/autoload_modules.php](core/autoload_modules.php)):

```
Ryra\Modules\UserProfile\Controllers\UserController
  → modules/user_profile/src/Controllers/UserController.php
```

```
modules/{ten_module}/
├── {ten_module}.module.php   # Khai báo module: controllers, providers, middlewares
├── src/
│   ├── Controllers/
│   ├── DTOs/
│   ├── Models/
│   ├── Services/
│   ├── Repositories/
│   ├── Middlewares/
│   ├── Providers/
│   └── Enums/
├── views/                    # Template Twig
└── tests/                    # Test Pest của module
```

## 6. Bắt đầu nhanh: tạo module đầu tiên

Ví dụ tạo module `book` có API trả về danh sách sách.

**Bước 1. Sinh module**

```bash
php ryra gen:module book
```

**Bước 2. Sinh controller, DTO, model**

```bash
php ryra gen:controller Book --module=book
php ryra gen:dto StoreBook --module=book
php ryra gen:model Book --module=book
```

Controller và provider được tự động đăng ký vào `book.module.php`.

**Bước 3. Viết controller**

```php
<?php

namespace Ryra\Modules\Book\Controllers;

use Ryra\Core\Http\JsonResponse;
use Ryra\Core\Http\Response;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Route\Attributes\Group;
use Ryra\Core\Route\Attributes\Post;
use Ryra\Modules\Book\DTOs\StoreBookDTO;

#[Group(prefix: 'api/books', name: 'books.')]
class BookController
{
    #[Get(uri: '/', name: 'index')]
    public function index(): JsonResponse
    {
        return Response::json(['data' => []]);
    }

    #[Get(uri: '{id:\d+}', name: 'show')]
    public function show(int $id): JsonResponse
    {
        return Response::json(['id' => $id]);
    }

    #[Post(uri: '/', name: 'store')]
    public function store(StoreBookDTO $dto): JsonResponse
    {
        // $dto đã được validate; nếu dữ liệu sai, framework tự trả lỗi
        return Response::json(['title' => $dto->title], 201);
    }
}
```

**Bước 4. Khai báo validate trong DTO**

```php
<?php

namespace Ryra\Modules\Book\DTOs;

use Ryra\Core\ValidatedDTO\Rules\IsRequired;
use Ryra\Core\ValidatedDTO\Rules\IsString;
use Ryra\Core\ValidatedDTO\Rules\Max;
use Ryra\Core\ValidatedDTO\ValidatedDTO;

class StoreBookDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    #[Max(255)]
    public $title;
}
```

**Bước 5. Khởi động lại server và gọi thử**

```bash
php ryra start:serve
curl http://localhost:9090/api/books
curl -X POST http://localhost:9090/api/books -H 'Content-Type: application/json' -d '{"title":"Dế Mèn"}'
```

> Swoole giữ code trong bộ nhớ, nên sau khi sửa code cần khởi động lại server, hoặc chạy với `--watch`.

Xem thêm module mẫu đầy đủ (có service, provider, view Twig) tại [modules/example/](modules/example/).

## 7. Các lệnh CLI

Chạy `php ryra list` để xem toàn bộ lệnh.

| Lệnh | Mô tả |
|------|-------|
| `php ryra start:serve [-W]` | Khởi động server (`-W` / `--watch`: tự reload khi sửa file) |
| `php ryra gen:module <tên>` | Tạo module mới |
| `php ryra gen:controller <tên> --module=<m>` | Tạo controller |
| `php ryra gen:dto <tên> --module=<m>` | Tạo ValidatedDTO |
| `php ryra gen:model <tên> --module=<m>` | Tạo model |
| `php ryra gen:middleware <tên> --module=<m>` | Tạo middleware |
| `php ryra gen:provider <tên> --module=<m>` | Tạo service provider |
| `php ryra gen:mail <tên> --module=<m>` | Tạo Mailable |
| `php ryra messenger:consume` | Chạy worker xử lý queue |
| `php ryra test --module=<m>` | Chạy test Pest trong `modules/<m>/tests` |

Các lệnh `gen:*` có thêm tuỳ chọn `-f` / `--force` để ghi đè file đã tồn tại.

## 8. Tài liệu chi tiết

Hướng dẫn chi tiết từng phần nằm trong [core/Guide/](core/Guide/):

| Chủ đề | File |
|--------|------|
| Tạo module | [module.md](core/Guide/module.md) |
| Controller & route (`#[Group]`, `#[Get]`, `#[Post]`, ...) | [controller.md](core/Guide/controller.md) |
| Đọc dữ liệu request (query, body, header, file upload, ...) | [request.md](core/Guide/request.md) |
| DTO & toàn bộ rule validate | [dto.md](core/Guide/dto.md) |
| Middleware (toàn cục / module / group / route) | [middleware.md](core/Guide/middleware.md) |
| Model, quan hệ, accessor/mutator, soft delete | [model.md](core/Guide/model.md) |
| Query Builder, raw query, transaction | [query-database.md](core/Guide/query-database.md) |
| Service có sẵn: DI, Cache, Redis, Log, Mail, Queue, Event, Hash, Snowflake | [services.md](core/Guide/services.md) |

Tài liệu riêng của một số package:

- AI (chat, streaming, tool calling, RAG, embedding): [core/AI/README.md](core/AI/README.md)
- Auth: [core/Auth/README.md](core/Auth/README.md)

### Middleware toàn cục và service provider

Khai báo trong file [ryra](ryra):

```php
App::getInstance()
    ->setBasePath(__DIR__)
    ->withMiddleware(static function (MiddlewareCollection $middlewares): void {
        $middlewares->use([
            HandleCors::class,
            HandleException::class,
            // thêm middleware toàn cục tại đây
        ]);
    })
    ->withProviders(static function (ServiceProviderManager $providers): void {
        // đăng ký thêm provider tại đây
    })
    ->start();
```

> **Lưu ý:** file `ryra` hiện đang dùng `Ryra\Modules\Common\Middlewares\LogRequest` và `SetLocaleFromHeader`, nhưng repo chưa có module `common`. Hãy tạo module `common` chứa 2 middleware này, hoặc xoá 2 dòng đó khỏi `ryra`. Nếu không, request sẽ báo lỗi không tìm thấy class.

## 9. Lưu ý khi dùng Swoole

Ứng dụng chạy liên tục trong bộ nhớ, không khởi động lại sau mỗi request như PHP-FPM:

- **Không lưu dữ liệu riêng của từng request vào biến `static` hay singleton.** Dữ liệu đó sẽ bị dùng chung giữa các request và giữa các người dùng. Thông tin người dùng hiện tại nên lấy qua `Context`.
- **Sửa code xong phải khởi động lại server**, hoặc chạy với `--watch` khi dev.
- Không dùng `exit()` / `die()` trong code xử lý request.
- I/O (DB, Redis, HTTP client) chạy theo coroutine nhờ `SWOOLE_HOOK_ALL`. Không dùng hàm blocking không được hook.
