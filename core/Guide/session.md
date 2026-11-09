# Session

Session lưu dữ liệu riêng của từng người dùng giữa các request, ví dụ thông tin đăng nhập trên web, giỏ hàng hay thông báo flash. API dùng token (JWT) thường không cần session.

## 1. Bật session

Session **không** được bật mặc định. Bạn cần thêm middleware `StartSession` cho các route muốn dùng session:

```php
use Ryra\Core\Session\Middleware\StartSession;

// Cho cả module: modules/web/web.module.php
'middlewares' => [
    StartSession::class,
],

// Hoặc cho một controller
#[Group(prefix: 'account', middlewares: [StartSession::class])]
class AccountController { /* ... */ }
```

Middleware này đọc session từ cookie khi request tới, rồi tự lưu lại khi request kết thúc.

Nếu cần đưa lỗi validate vào view Twig dưới biến `errors`, thêm middleware `Ryra\Core\Twig\Middleware\ShareErrorsFromSession` **sau** `StartSession`.

## 2. Cấu hình

File [config/session.php](../../config/session.php):

```dotenv
SESSION_DRIVER=file          # file | database | null
SESSION_LIFETIME=120         # số phút
SESSION_COOKIE=vietiso_session
SESSION_SECURE_COOKIE=true   # bật khi chạy HTTPS
SESSION_SAME_SITE=lax
```

| Driver | Lưu ở đâu |
|--------|-----------|
| `file` | `storage/vietiso/sessions/` |
| `database` | Bảng `sessions` (đổi bằng `SESSION_TABLE`) |
| `null` | Không lưu (dùng khi test) |

Khi chạy nhiều server phía sau load balancer, hãy dùng `database`.

Driver `database` **không** tự tạo bảng. Bạn cần tạo trước:

```sql
CREATE TABLE sessions (
    id            VARCHAR(255) PRIMARY KEY,
    user_id       BIGINT NULL,
    ip_address    VARCHAR(45) NULL,
    user_agent    TEXT NULL,
    payload       LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    INDEX (last_activity)
);
```

## 3. Sử dụng

```php
use Ryra\Core\Session\Facade\Session;

Session::put('cart', ['book-1' => 2]);
Session::get('cart', []);              // kèm giá trị mặc định
Session::has('cart');
Session::pull('cart');                 // lấy ra rồi xoá
Session::forget('cart');
Session::push('viewed', $bookId);      // thêm vào mảng
Session::increment('visits');
Session::flush();                      // xoá toàn bộ

Session::regenerateToken();            // đổi CSRF token, nên gọi sau khi đăng nhập
Session::id();
```

Hoặc lấy từ request:

```php
public function index(Request $request): Response
{
    if ($request->hasSession()) {
        $userId = $request->session()->get('user_id');
    }
}
```

## 4. Flash: dữ liệu chỉ dùng cho request kế tiếp

```php
// Thường dùng khi redirect
return Response::redirect()->back()
    ->with('success', 'Đã lưu thành công');

// Trong request kế tiếp
Session::get('success');
```

Lấy lại dữ liệu người dùng đã nhập sau khi validate lỗi:

```php
return Response::redirect()->back()->withErrors($errors)->withInput();

// Request sau
$request->session()->getOldInput('email');
$request->session()->getErrors();
```

Trong Twig, dùng hàm `old('email')` để điền lại giá trị vào form.

## 5. Ghi nhớ

- Phải có `StartSession` thì `Session::...` và `Response::redirect()->back()` mới hoạt động.
- Mỗi request có một đối tượng session riêng, nên dữ liệu không bị lẫn giữa các người dùng.
