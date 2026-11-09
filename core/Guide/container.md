# Container & Service Provider

Container quản lý việc tạo đối tượng (service) và truyền chúng vào nơi cần dùng (dependency injection). Service Provider là nơi bạn đăng ký service vào container.

---

## 1. Quy tắc quan trọng nhất

> **Container chỉ tự inject những class đã được đăng ký.**
>
> Nếu constructor của controller cần `BookService` mà bạn chưa đăng ký `BookService` trong một provider, tham số đó sẽ nhận `null` và PHP báo `TypeError`. Container **không** tự tạo class chưa đăng ký.

Vì vậy, mỗi service, repository hay interface bạn muốn inject đều phải được bind trong `register()` của một provider.

---

## 2. Tạo Service Provider

```bash
php ryra gen:provider Book --module=book
```

Provider được tự đăng ký vào mảng `providers` trong `book.module.php`.

```php
<?php

namespace Ryra\Modules\Book\Providers;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Modules\Book\Repositories\BookRepository;
use Ryra\Modules\Book\Services\BookService;
use Ryra\Modules\Book\Services\BookServiceInterface;

class BookServiceProvider extends ServiceProvider
{
    // Chỉ dùng để đăng ký service vào container
    public function register(): void
    {
        $this->app->singleton(BookRepository::class, BookRepository::class);

        // Callback nhận container, dùng để tạo object có tham số
        $this->app->singleton(BookServiceInterface::class, fn ($app) => new BookService(
            $app->get(BookRepository::class),
        ));
    }

    // Chạy sau khi MỌI provider đã register xong, dùng service của provider khác ở đây
    public function boot(): void
    {
        //
    }
}
```

- `register()` của tất cả provider chạy trước, sau đó mới tới `boot()`.
- Provider chạy theo đúng thứ tự khai báo trong `.module.php`.

---

## 3. Các kiểu đăng ký

| Hàm | Vòng đời | Khi nào dùng |
|-----|----------|--------------|
| `singleton($id, $callback)` | Tạo **1 lần** cho cả vòng đời server | Service không giữ dữ liệu riêng của request (repository, client, service tính toán, ...) |
| `scoped($id, $callback)` | Tạo 1 lần **cho mỗi request (coroutine)** | Service giữ trạng thái riêng của request (người dùng hiện tại, giỏ hàng, ...) |
| `transient($id, $callback)` | Tạo **mới mỗi lần** lấy ra | Đối tượng dùng một lần, có trạng thái thay đổi |

Mỗi hàm có thêm bản `...If()` (`singletonIf`, `scopedIf`, `transientIf`) để chỉ đăng ký khi `$id` chưa tồn tại.

`$callback` có thể là:
- **tên class**: container gọi `new $class()` **không truyền tham số**
- **closure** `fn ($app) => ...`: tự tạo object, `$app` là container

> Vì server Swoole chạy liên tục, **singleton được dùng chung cho mọi request và mọi người dùng**. Tuyệt đối không lưu dữ liệu của request (user, input, ...) vào thuộc tính của singleton. Dùng `scoped` hoặc `Context` thay thế.

### Alias

```php
$this->app->singleton('book.service', fn () => new BookService());
$this->app->alias('book.service', BookServiceInterface::class);
// Giờ cả 'book.service' và BookServiceInterface::class đều trỏ tới cùng service
```

---

## 4. Lấy service ra dùng

### Inject qua constructor (khuyên dùng)

Dùng được trong controller, và trong class được tạo bằng `App::make()` hoặc container:

```php
class BookController
{
    public function __construct(
        protected BookServiceInterface $books,
    ) {}
}
```

### Inject vào thuộc tính bằng `#[Inject]`

```php
use Ryra\Core\Container\Inject;
use Ryra\Core\Config\Repository;

class ReportService
{
    #[Inject]
    protected BookServiceInterface $books;   // lấy theo kiểu của thuộc tính

    #[Inject('config')]
    protected Repository $config;            // lấy theo id chỉ định
}
```

`#[Inject]` chỉ có tác dụng với object do container tạo ra: service lấy từ container, hoặc object tạo bằng `App::make()`.

### Lấy trực tiếp

```php
use Ryra\Core\App;

$books = App::service(BookServiceInterface::class);
$books = app(BookServiceInterface::class);           // helper tương đương
App::hasService(BookServiceInterface::class);        // kiểm tra đã đăng ký chưa
```

### Tạo object có tự inject

```php
// Tạo object mới, tự inject các tham số đã đăng ký trong container
$report = App::make(ReportService::class);

// Truyền thêm tham số theo tên
$report = App::make(ReportService::class, ['year' => 2026]);

// Gọi một method, tự inject tham số
App::call([ReportService::class, 'export']);
```

---

## 5. Middleware và provider toàn cục

Ngoài provider của module, bạn có thể đăng ký provider toàn cục trong file [`ryra`](../../ryra):

```php
->withProviders(static function (ServiceProviderManager $providers): void {
    $providers->register(\App\Providers\AppServiceProvider::class);
})
```

Danh sách provider của core nằm ở [core/providers.php](../providers.php).
