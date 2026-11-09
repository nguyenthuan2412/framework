# Gửi mail

Mail dùng [Symfony Mailer](https://symfony.com/doc/current/mailer.html) bên dưới. Hỗ trợ SMTP, SendGrid và Mailtrap.

## 1. Cấu hình

File [config/mail.php](../../config/mail.php):

```dotenv
MAIL_MAILER=smtp               # smtp | sendgrid | mailtrap | roundrobin
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=you@gmail.com
MAIL_PASSWORD=app-password
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Ryra Shop"

# Khi dùng SendGrid
MAIL_SEND_GRID_API_KEY=
```

`MAIL_FROM_ADDRESS` và `MAIL_FROM_NAME` là người gửi mặc định của mọi mail.

Driver `roundrobin` gửi luân phiên qua các mailer liệt kê trong `mailers.roundrobin`. Khi một mailer lỗi, nó tự chuyển sang mailer tiếp theo.

## 2. Gửi nhanh

```php
use Ryra\Core\Mail\Facade\Mail;

Mail::to('an@example.com')
    ->subject('Chào mừng bạn')
    ->html('<h1>Xin chào An</h1>')
    ->send();

// Nội dung text thuần
Mail::to('an@example.com')->subject('OTP')->text('Mã của bạn: 123456')->send();

// Nội dung từ view Twig
Mail::to('an@example.com')
    ->subject('Chào mừng bạn')
    ->view('@user/mails/welcome.twig', ['name' => 'An'])
    ->send();
```

Các hàm đặt người nhận / người gửi (gọi nối tiếp được):

```php
Mail::from('sales@example.com')                // ghi đè người gửi mặc định
    ->to('an@example.com', 'binh@example.com') // nhiều người nhận
    ->cc('ke-toan@example.com')
    ->bcc('log@example.com')
    ->replyTo('support@example.com')
    ->subject('...')
    ->html('...')
    ->send();
```

Muốn kèm tên hiển thị, truyền `Symfony\Component\Mime\Address`:

```php
use Symfony\Component\Mime\Address;

Mail::to(new Address('an@example.com', 'Nguyễn An'))->subject('...')->html('...')->send();
```

## 3. Class Mailable

Với mail dùng lại nhiều lần, hãy tạo class Mailable:

```bash
php ryra gen:mail OrderPaid --module=order
```

Lệnh tạo file `modules/order/src/Mails/OrderPaid.php`:

```php
<?php

namespace Ryra\Modules\Order\Mails;

use Ryra\Core\Mail\Mailable;
use Ryra\Core\Mail\Mailables\Attachment;
use Ryra\Core\Mail\Mailables\ContentInterface;
use Ryra\Core\Mail\Mailables\Envelope;
use Ryra\Core\Mail\Mailables\Headers;
use Ryra\Core\Mail\Mailables\View;

class OrderPaid extends Mailable
{
    public function __construct(
        public int $orderId,
        public int $total,
    ) {}

    // Tiêu đề mail
    public function envelope(): Envelope
    {
        return new Envelope(subject: "Đơn hàng #{$this->orderId} đã thanh toán");
    }

    // Nội dung mail
    public function content(): ContentInterface
    {
        return new View('@order/mails/OrderPaid.twig', [
            'orderId' => $this->orderId,
            'total' => $this->total,
        ]);
    }

    // File đính kèm (tuỳ chọn)
    public function attachments(): ?array
    {
        return [
            Attachment::fromStorage("invoices/{$this->orderId}.pdf")->as('hoa-don.pdf'),
        ];
    }

    // Header bổ sung (tuỳ chọn)
    public function headers(): ?Headers
    {
        return new Headers(text: ['X-Order-Id' => (string) $this->orderId]);
    }
}
```

View đặt tại `modules/order/views/mails/OrderPaid.twig`.

Gửi:

```php
Mail::to($customer->email)->send(new OrderPaid($order->id, $order->total));

// Kèm cc / bcc
Mail::to($customer->email)
    ->cc('ke-toan@example.com')
    ->send(new OrderPaid($order->id, $order->total));
```

### Các kiểu nội dung

| Class | Mô tả |
|-------|-------|
| `View($path, $variables)` | Render view Twig thành HTML |
| `Markdown($markdown)` | Chuyển chuỗi Markdown thành HTML |
| `Html($html)` | HTML có sẵn |
| `Text($text)` | Text thuần |

```php
use Ryra\Core\Mail\Mailables\Markdown;

public function content(): ContentInterface
{
    return new Markdown("# Cảm ơn bạn\nĐơn **#{$this->orderId}** đã được thanh toán.");
}
```

### File đính kèm

```php
Attachment::fromPath('/var/data/report.pdf');                  // đường dẫn tuyệt đối
Attachment::fromStorage('invoices/1.pdf');                     // tính từ thư mục storage/
Attachment::fromData(fn () => $csvContent, 'export.csv');      // nội dung sinh lúc chạy

Attachment::fromPath($path)->as('ten-hien-thi.pdf')->withMime('application/pdf');
```

### Tuỳ biến thêm bằng `using`

`using()` nhận đối tượng `Symfony\Component\Mime\Email`, dùng để làm những việc không có hàm riêng:

```php
use Symfony\Component\Mime\Email;

public function envelope(): Envelope
{
    return (new Envelope(subject: 'Khẩn'))
        ->using(fn (Email $email) => $email->priority(Email::PRIORITY_HIGH));
}
```

## 4. Gửi sau khi trả response

Gửi mail qua SMTP có thể mất vài giây. Dùng `defer()` để mail được gửi **sau khi** request xử lý xong, người dùng không phải chờ:

```php
Mail::to($user->email)->defer(new WelcomeMail($user->name));

return Response::json(['message' => 'Đăng ký thành công'], 201);
```

## 5. Chọn mailer khác mailer mặc định

```php
Mail::driver('sendgrid')->to('an@example.com')->subject('...')->html('...')->send();
```

## 6. Ghi nhớ

- Mỗi lần gọi `Mail::...` sẽ tạo một mail mới, nên người nhận và nội dung không bị lẫn giữa các lần gửi.
- Đặt người nhận bằng `Mail::to()`, `cc()`, `bcc()` trước khi gọi `send()`. `Envelope` của Mailable dùng để khai báo tiêu đề và tuỳ biến `using`.
- Khi dev, có thể dùng [Mailtrap](https://mailtrap.io) (`MAIL_MAILER=mailtrap`) để xem mail mà không gửi thật.
