# Ghi log

Ryra dùng [Monolog](https://github.com/Seldaek/monolog) để ghi log. Mặc định log được ghi vào `storage/vietiso/logs/vietiso.log`.

## 1. Ghi log

```php
use Ryra\Core\Log\Log;

Log::debug('Giá trị tạm', ['x' => $x]);
Log::info('User created', ['id' => $user->id]);
Log::notice('Cấu hình sắp hết hạn');
Log::warning('Deprecated method used');
Log::error('Thanh toán thất bại', ['order' => $orderId, 'exception' => $e]);
Log::critical('Mất kết nối database');
Log::alert('...');
Log::emergency('...');

// Ghi exception trực tiếp
Log::error($e);
```

Tham số thứ hai (`context`) là mảng dữ liệu kèm theo, được ghi ra dưới dạng JSON ở cuối dòng log.

## 2. Logger theo tên / nhóm

```php
$logger = Log::get('payment');              // tên 'payment', nhóm cấu hình 'default'
$logger->info('Callback received', $payload);

$logger = Log::get('payment', 'payment');   // dùng nhóm cấu hình 'payment'
```

Tên logger xuất hiện trong mỗi dòng log, giúp lọc log theo chức năng. Mỗi cặp tên + nhóm chỉ được tạo một lần rồi dùng lại.

## 3. Cấu hình

File [config/logger.php](../../config/logger.php). Mỗi khoá gốc là một **nhóm**, gồm danh sách `handlers` (ghi đi đâu) và `processors` (thêm thông tin vào log).

Ví dụ thêm nhóm `payment` ghi ra file riêng, mỗi ngày một file:

```php
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

return [
    'default' => [ /* ... giữ nguyên ... */ ],

    'payment' => [
        'handlers' => [
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => storage_path(join_paths('vietiso', 'logs', 'payment.log')),
                    'maxFiles' => 30,
                    'level' => Level::Info,
                ],
            ],
        ],
        'processors' => [],
    ],
];
```

`constructor` là các tham số truyền vào constructor của handler, đặt theo **tên tham số**. Có thể khai báo thêm `formatter` cho từng handler, giống nhóm `default`.

## 4. Ghi nhớ

- Lỗi chưa được bắt trong request được `HandleException` tự ghi log.
- Không ghi mật khẩu, token hay số thẻ vào log.
- Log của chính Swoole server nằm riêng ở `storage/vietiso/logs/swoole.log`.
