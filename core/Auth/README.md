# Component Auth

Xác thực người dùng qua nhiều cách khác nhau (JWT, API token, HTTP Basic,
session) sau cùng một API. Đổi cách xác thực chỉ là đổi cấu hình guard trong
`config/auth.php`, code nghiệp vụ vẫn gọi `auth()->user()`.

## Kiến trúc

Theo mô hình Manager + Driver như `core/Cache` và `core/AI`, nhưng tách làm hai
trục:

| Khái niệm | Trả lời câu hỏi | Cài đặt sẵn |
|---|---|---|
| **Guard** (driver) | Lấy thông tin xác thực từ request bằng cách nào? | `jwt`, `bearer`, `basic`, `session` |
| **User provider** | Lấy dữ liệu người dùng ở đâu ra? | `model` |

| Service | Facade | Vai trò |
|---|---|---|
| `auth` | `Auth` | Quản lý guard, đăng ký driver/provider tuỳ biến |
| `auth.driver` | — | Guard mặc định của request hiện tại |

Guard nói chuyện qua contract trong `core/Auth/Contracts/`, nên module nghiệp vụ
chỉ phụ thuộc vào `Guard` chứ không phụ thuộc driver cụ thể.

Service `auth` được bind dạng **scoped** — mỗi coroutine (mỗi request) có một
manager riêng, trạng thái đăng nhập không rò rỉ giữa các request.

## Chuẩn bị model

Model dùng cho xác thực phải implement `Authenticatable`. Trait cùng tên đã cài
sẵn 4 method, chỉ khai báo thêm khi cột mật khẩu không phải `password`:

```php
use Ryra\Core\Auth\Contracts\Authenticatable as AuthenticatableContract;
use Ryra\Core\Auth\Traits\Authenticatable;
use Ryra\Core\Database\Model\Model;

class Admin extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected string $table = 'admins';

    // Mặc định là 'password'
    protected string $authPasswordName = 'password';
}
```

Khai báo model trong `config/auth.php`:

```php
'providers' => [
    'admins' => [
        'driver' => 'model',
        'model' => Admin::class,

        // Điều kiện luôn áp dụng cho mọi truy vấn xác thực
        'conditions' => ['deleted_at' => null],

        // Driver băm mật khẩu, null là mặc định của config/hashing.php
        'hash' => null,
    ],
],
```

## Sử dụng

```php
use Ryra\Core\Auth\Facade\Auth;

Auth::user();          // ?Authenticatable, null nếu chưa đăng nhập
Auth::id();            // định danh người dùng
Auth::check();         // đã đăng nhập chưa
Auth::authenticate();  // trả về user, ném lỗi 401 nếu chưa đăng nhập

Auth::guard('guide')->user();   // chỉ định guard cụ thể
auth('guide')->user();          // helper, tương đương dòng trên
auth()->guard('admin')->id();
```

`Auth::authenticate()` ném `AuthenticationException` (kế thừa `HttpException`
401) nên `core/Http/Exception/Handler` tự render thành JSON `{"message": ...}`
với status 401, không cần bắt lỗi thủ công.

## Driver `jwt`

```php
'guards' => [
    'admin' => [
        'driver' => 'jwt',
        'provider' => 'admins',

        // Claim bắt buộc phải khớp, đồng thời được nhúng khi phát hành token
        'claims' => ['role' => 'admin'],

        // Ghi đè riêng cho guard này, còn lại kế thừa khối 'jwt' chung
        'ttl' => 3600,
    ],
],
```

Token được đọc theo thứ tự: header `Authorization: Bearer`, rồi `input_key`
(query/body), rồi `cookie` — hai cái sau mặc định tắt.

```php
$jwt = Auth::guard('admin');

$token = $jwt->attempt(['email' => $email, 'password' => $password]);  // null nếu sai
$token = $jwt->login($admin);        // phát hành token cho user có sẵn
$token = $jwt->loginUsingId($id);

$jwt->payload();          // ?Ryra\Core\Jwt\Token
$jwt->claim('role');      // đọc claim từ token của request hiện tại
$jwt->token();            // chuỗi token thô client gửi lên

$newToken = $jwt->refresh();   // thu hồi token cũ, phát hành token mới
$jwt->logout();               // đưa token hiện tại vào blacklist
```

`claims` vừa là điều kiện lọc vừa là dữ liệu phát hành: token thiếu hoặc sai
`role` sẽ bị coi như chưa đăng nhập, nên hai guard `admin` và `guide` dùng chung
một khoá ký vẫn không xài chéo token của nhau được.

Muốn nhúng thêm claim lấy từ model (email, tên...), cho model implement
`JwtSubject`:

```php
class Admin extends Model implements AuthenticatableContract, JwtSubject
{
    public function getJwtIdentifier(): string|int
    {
        return (string) $this->getKey();
    }

    public function getJwtCustomClaims(): array
    {
        return ['email' => $this->email, 'name' => $this->name];
    }
}
```

`logout()` và `refresh()` chỉ thu hồi được token khi bật `'blacklist' => true`
(mặc định tắt). Blacklist dùng lại `core/Jwt/Blacklist`, driver mặc định là
`redis`, đổi bằng `blacklist_driver` (`redis`, `database`, `file`).

## Driver `bearer`

API token lưu trong một cột của bảng dữ liệu — hợp với token dài hạn cho
tích hợp máy-với-máy, không hết hạn theo thời gian như JWT.

```php
'api' => [
    'driver' => 'bearer',
    'provider' => 'admins',
    'storage_key' => 'api_token',  // cột lưu token
    'hash' => true,                // lưu sha256, chỉ client giữ token gốc
    'length' => 60,
    'header' => 'X-Api-Token',     // header phụ ngoài Bearer
],
```

```php
$plain = Auth::guard('api')->login($admin);  // cấp token mới, ghi đè token cũ
Auth::guard('api')->logout();                // xoá token đã lưu
```

## Driver `basic`

```php
'basic' => [
    'driver' => 'basic',
    'provider' => 'admins',
    'username_field' => 'email',
    'realm' => 'OneGuide',
],
```

Khi chưa xác thực, `authenticate()` trả 401 kèm header `WWW-Authenticate: Basic
realm="..."` để trình duyệt hiện hộp thoại đăng nhập.

## Driver `session`

```php
$web = Auth::guard('web');

$web->attempt(['email' => $email, 'password' => $password]);  // bool
$web->once(['email' => $email, 'password' => $password]);     // không ghi session
$web->login($user);
$web->loginUsingId($id);
$web->logout();
```

Cần middleware `StartSession` chạy trước. Định danh người dùng lưu theo khoá
riêng của từng guard nên hai guard session không đè lên nhau.

**Chưa hỗ trợ remember-me** — framework không có cơ chế queue cookie, muốn có
phải thêm middleware ghi cookie sau response.

## Middleware

Middleware trong framework khai báo bằng **tên class** nên không truyền tham số
được. Mỗi guard tạo một lớp con:

```php
namespace Ryra\Modules\Admin\Middlewares;

use Ryra\Core\Auth\Middleware\Authenticate;

class AuthAdmin extends Authenticate
{
    protected ?string $guard = 'admin';
}
```

```php
#[Group(prefix: 'api/admins', middlewares: [AuthAdmin::class])]
class AdminController { }
```

Middleware gọi `authenticate()` (ném 401 nếu chưa đăng nhập) rồi `shouldUse()`,
nên các đoạn code phía sau chỉ cần `auth()->user()` là ra đúng guard đó.

## Thêm driver mới

```php
// Trong ServiceProvider của module
Auth::extend('sso', fn (string $name, UserProvider $provider, array $config, App $app)
    => new SsoDriver($name, $provider, $config));

Auth::provider('ldap', fn (array $config, App $app) => new LdapUserProvider($config));
```

Driver mới nên kế thừa `AbstractDriver` (đã có sẵn `user/check/id/validate/
authenticate/setUser`) và chỉ cài `resolveUser()`. Cài thêm `TokenGuard` hoặc
`StatefulGuard` nếu có phát hành token / lưu phiên.

## Lưu ý

- `AbstractDriver` cache người dùng đã phân giải trong phạm vi request; gọi
  `forgetUser()` khi cần phân giải lại.
- Guard không tự phân biệt vai trò. Muốn tách quyền theo vai trò thì dùng
  `claims` (jwt) hoặc `conditions` của provider, hoặc kết hợp `core/Permission`.
- Token sai định dạng, sai chữ ký, hết hạn, hoặc nằm trong blacklist đều được
  coi là "chưa đăng nhập" (`user()` trả null) chứ không ném exception — nơi
  quyết định chặn hay không là `authenticate()` / middleware.
