# Sử dụng các Service có sẵn của Core

## Dependency Injection
```php
// Lấy từ container
$service = App::service(SomeClass::class);

// Hoặc inject qua constructor (trong controller/service)
public function __construct(
    private SomeServiceInterface $service
) {}
```

## Snowflake ID
```php
$id = Snowflake::id(); // Sinh ID duy nhất, dùng được cho hệ thống phân tán
```

## Hashing
```php
$hash = Hash::make($password);           // Băm mật khẩu
$check = Hash::check($password, $hash);  // Kiểm tra mật khẩu
```

## Event
```php
// Phát event
Event::dispatch(new UserRegistered($user));

// Lắng nghe event (qua EventServiceProvider)
Event::listen(UserRegistered::class, SendWelcomeEmailListener::class);
```

## Cache (Redis)
```php
Cache::set('key', $value, 3600);    // Lưu trong 1 giờ
Cache::get('key');                   // Đọc
Cache::delete('key');                // Xoá
Cache::remember('key', 3600, fn() => db query); // Có cache thì đọc, chưa có thì chạy hàm rồi lưu
```

## Redis
```php
$redis = Redis::connection();
$redis->set('key', 'value');
$redis->get('key');
```

## Ghi log
```php
Log::info('User created', ['id' => $id]);
Log::error('Something failed', ['exception' => $e]);
Log::warning('Deprecated method used');
```

## Gửi mail
```php
Mail::send($email); // Gửi email (cấu hình trong config/mail.php)
```

## Queue / Messenger
```php
// Đẩy job chạy bất đồng bộ
Messenger::dispatch(new SendEmailJob($data));

// Hoặc chạy trễ (tính bằng giây)
Messenger::dispatch(new SendEmailJob($data))->delay(3600);
```

Để xử lý job trong queue, chạy worker: `php ryra messenger:consume`.

## Context (người dùng / doanh nghiệp hiện tại)
```php
Context::get('user.user_id');       // ID người dùng đang đăng nhập
Context::get('user.business_id');   // ID doanh nghiệp hiện tại
Context::get('user.role');          // Vai trò của người dùng
```

## Ghi nhớ
- Mọi service của core đều dùng được qua facade
- Trong controller/service nên ưu tiên inject qua constructor
- `Context` dùng để lấy thông tin người dùng và doanh nghiệp của request hiện tại
