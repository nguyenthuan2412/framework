# Event

Event giúp tách các xử lý phụ ra khỏi luồng chính. Ví dụ: sau khi đăng ký tài khoản thì gửi email chào mừng và ghi log, nhưng service đăng ký không cần biết tới hai việc đó.

## 1. Tạo class event

Event là một class bình thường, chứa dữ liệu cần truyền đi:

```php
<?php

namespace Ryra\Modules\User\Events;

use Ryra\Core\Event\Dispatchable;

class UserRegistered
{
    use Dispatchable;   // tuỳ chọn, giúp gọi UserRegistered::dispatch(...)

    public function __construct(
        public readonly int $userId,
        public readonly string $email,
    ) {}
}
```

## 2. Tạo listener

Listener là class có một method xử lý. Method này nhận event làm tham số:

```php
<?php

namespace Ryra\Modules\User\Listeners;

use Ryra\Modules\User\Events\UserRegistered;

class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        // gửi email tới $event->email
    }
}
```

Listener được tạo bằng `App::make()`, nên constructor của listener cũng được inject service như bình thường.

## 3. Đăng ký listener

Đăng ký trong `boot()` của service provider của module:

```php
use Ryra\Core\Event\Facade\Event;

public function boot(): void
{
    // Dạng [class, method]
    Event::listen(UserRegistered::class, [SendWelcomeEmail::class, 'handle']);

    // Hoặc closure
    Event::listen(UserRegistered::class, function (UserRegistered $event) {
        Log::info('User registered', ['id' => $event->userId]);
    });
}
```

> `listen()` chỉ nhận **closure** hoặc **mảng `[Class::class, 'method']`**. Truyền tên class trơn như `SendWelcomeEmail::class` sẽ gây lỗi `TypeError`.

### Subscriber: gom nhiều listener vào một class

```php
use Ryra\Core\Contracts\Event\EventSubscriber;

class UserEventSubscriber implements EventSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegistered::class => 'onRegistered',
            UserDeleted::class    => ['onDeleted', 'cleanup'],  // nhiều method
        ];
    }

    public function onRegistered(UserRegistered $event): void { /* ... */ }
    public function onDeleted(UserDeleted $event): void { /* ... */ }
    public function cleanup(UserDeleted $event): void { /* ... */ }
}

// Trong boot() của provider
Event::subscriber(UserEventSubscriber::class);
```

## 4. Phát event

```php
use Ryra\Core\Event\Facade\Event;

Event::dispatch(new UserRegistered($user->id, $user->email));

// Nếu event dùng trait Dispatchable
UserRegistered::dispatch($user->id, $user->email);
UserRegistered::dispatchIf($isNew, $user->id, $user->email);
UserRegistered::dispatchUnless($isImport, $user->id, $user->email);
```

### Event dạng chuỗi (không cần class)

```php
Event::listen('order.paid', function (int $orderId) { /* ... */ });

Event::dispatch('order.paid', [$orderId]);   // payload truyền thành tham số của listener
```

## 5. Ghi nhớ

- Listener chạy **đồng bộ**, theo thứ tự đăng ký, ngay trong request hiện tại. Với việc chạy lâu (gửi mail, gọi API), có thể chạy nền bằng `Ryra\Core\Coroutine\Coroutine::create(fn () => ...)`.
- Listener trả về `false` sẽ **dừng** các listener phía sau.
- `Event::clearListeners(UserRegistered::class)` xoá mọi listener của một event.
- Nên đăng ký listener theo đúng tên event (tên class), không dùng ký tự `*`.
