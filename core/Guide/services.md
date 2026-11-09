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

// Lắng nghe event (đăng ký trong boot() của provider)
Event::listen(UserRegistered::class, [SendWelcomeEmailListener::class, 'handle']);
```

Chi tiết: [event.md](event.md)

## Cache (Redis)
```php
Cache::set('key', $value, 3600);    // Lưu trong 1 giờ
Cache::get('key');                   // Đọc
Cache::delete('key');                // Xoá
Cache::remember('key', 3600, fn() => db query); // Có cache thì đọc, chưa có thì chạy hàm rồi lưu
```

Chi tiết: [cache.md](cache.md)

## Redis
```php
$redis = Redis::connection();
$redis->set('key', 'value');
$redis->get('key');
```

Chi tiết: [cache.md](cache.md#3-redis)

## Ghi log
```php
Log::info('User created', ['id' => $id]);
Log::error('Something failed', ['exception' => $e]);
Log::warning('Deprecated method used');
```

Chi tiết: [log.md](log.md)

## Gửi mail
```php
use Ryra\Core\Mail\Facade\Mail;

Mail::to('user@example.com')
    ->subject('Xin chào')
    ->html('<p>Nội dung</p>')
    ->send();

Mail::to('user@example.com')->subject('Xin chào')->view('@user/mails/welcome.twig', ['name' => 'An'])->send();
```

Chi tiết: [mail.md](mail.md)

## Context: dữ liệu riêng của request hiện tại

`Context` lưu dữ liệu **riêng cho từng request (coroutine)**. Đây là chỗ an toàn để giữ thông tin của request, thay cho biến `static` hay thuộc tính của singleton.

```php
use Ryra\Core\Context\Context;

// Thường ghi trong middleware, ví dụ sau khi xác thực
Context::put('tenant.id', $tenantId);
Context::push('trace', 'auth-ok');       // thêm vào mảng

// Đọc ở bất kỳ đâu trong cùng request
Context::get('tenant.id');
Context::get('tenant.plan', 'free');     // kèm giá trị mặc định
Context::has('tenant.id');
```

- Dữ liệu tự bị xoá khi request kết thúc.
- Coroutine con tạo bằng `Ryra\Core\Coroutine\Coroutine::create()` nhận một **bản sao** Context của coroutine cha (sửa ở con không ảnh hưởng cha). Tạo bằng `go()` hay `\Swoole\Coroutine::create()` thuần thì **không** có Context.
- Core không tự ghi thông tin người dùng vào Context. Muốn lấy người dùng đang đăng nhập, dùng `auth()->user()` (xem [core/Auth/README.md](../Auth/README.md)).

## Ghi nhớ
- Mọi service của core đều dùng được qua facade
- Trong controller/service nên ưu tiên inject qua constructor (xem [container.md](container.md))
- Dữ liệu riêng của request lưu vào `Context`, không lưu vào biến `static` hay singleton
