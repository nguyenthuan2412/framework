# Cấu hình, biến môi trường & hàm helper

## 1. File `.env`

Đặt ở thư mục gốc dự án. Mỗi dòng là một biến `KEY=value`. File này **không** được commit (đã có trong `.gitignore`).

```dotenv
APP_ENV=local
APP_DEBUG=true
DB_HOST=127.0.0.1
```

Đọc giá trị bằng `env()`:

```php
env('APP_DEBUG');            // true (tự chuyển kiểu)
env('DB_PORT', 3306);        // có giá trị mặc định
```

`env()` tự chuyển các chuỗi `true` → `true`, `false` → `false`, `null` → `null` (không phân biệt hoa thường). Các giá trị khác giữ nguyên dạng chuỗi.

> Chỉ nên gọi `env()` **bên trong các file của thư mục `config/`**. Trong code ứng dụng, hãy dùng `config()`.

## 2. File cấu hình trong `config/`

Mỗi file trong [config/](../../config/) trả về một mảng. Tên file chính là khoá gốc.

```php
// config/payment.php
return [
    'default' => env('PAYMENT_GATEWAY', 'vnpay'),
    'gateways' => [
        'vnpay' => [
            'tmn_code' => env('VNPAY_TMN_CODE'),
        ],
    ],
];
```

Đọc bằng `config()` với dấu chấm để đi sâu vào mảng:

```php
config('payment.default');                    // 'vnpay'
config('payment.gateways.vnpay.tmn_code');
config('payment.timeout', 30);                // mặc định nếu không có
config('app.name');
```

Hoặc dùng `Repository` qua container:

```php
use Ryra\Core\Config\Repository;

$config = app('config');          // Repository
$config->get('app.name');
$config->has('payment.default');
$config->set('payment.default', 'momo');
```

> `config` là singleton dùng chung cho mọi request. Gọi `set()` lúc đang xử lý request sẽ làm thay đổi cấu hình của **tất cả** request khác. Chỉ nên `set()` trong `register()` / `boot()` của provider.

## 3. Môi trường chạy

```php
use Ryra\Core\App;

App::environment();                       // 'local', 'production', ...
App::environment('local');                // true/false
App::environment(['local', 'staging']);   // true nếu khớp một trong các giá trị
App::isProduction();
App::isLocal();
App::isStaging();
```

## 4. Các hàm helper toàn cục

| Hàm | Mô tả |
|-----|-------|
| `env($key, $default)` | Đọc biến môi trường |
| `config($key, $default)` | Đọc cấu hình |
| `app($id = null)` | Lấy container, hoặc một service theo id |
| `base_path($path)` | Đường dẫn tuyệt đối tính từ thư mục gốc dự án |
| `storage_path($path)` | Đường dẫn trong thư mục `storage/` |
| `public_path($path)` | Đường dẫn trong thư mục gốc của disk mặc định (mặc định là `storage/public/`) |
| `join_paths(...$paths)` | Nối các đoạn đường dẫn bằng dấu phân cách của hệ điều hành |
| `now()` | Thời điểm hiện tại (`Date`, kế thừa Carbon) |
| `collect($items)` | Tạo `Collection` (xem [collection.md](collection.md)) |
| `str($string)` | Tạo `Stringable` để xử lý chuỗi nối tiếp |
| `route($name, $params)` | Sinh URL từ tên route (xem [url.md](url.md)) |
| `url($path, $query)` | Sinh URL tuyệt đối |
| `t($key, $params)` | Dịch ngôn ngữ (xem [translation.md](translation.md)) |
| `auth($guard)` | Lấy auth manager / guard (xem [core/Auth/README.md](../Auth/README.md)) |
| `asset($path, $module)` | URL tới file tĩnh của module |
| `dump(...)` / `dd(...)` | In giá trị để debug (`dd` dừng xử lý request) |
