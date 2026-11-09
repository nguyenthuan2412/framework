# gRPC

Viết service và message bằng **class PHP + attribute**, framework tự sinh file `.proto`. Bạn không phải viết `.proto` bằng tay.

Bên dưới dùng các thư viện chính chủ:

- [google/protobuf](https://github.com/protocolbuffers/protobuf/tree/main/php): encode/decode message.
- `protoc`: compile `.proto` ra class PHP.
- HTTP/2 của Swoole: transport (giống Hyperf). Server gRPC chạy **chung port** với HTTP server.

Cách hoạt động:

```
#[Message] / #[Service] (class PHP)
        │  php ryra grpc:generate
        ▼
storage/grpc/proto/*.proto  ──protoc──▶  storage/grpc/php/GrpcStub/... (class google/protobuf)
        │
        ▼
Lúc chạy: DTO của bạn ⇄ class google/protobuf ⇄ binary
```

## 1. Cài đặt

Cần có `protoc`:

```bash
brew install protobuf                                   # macOS
apt-get install -y protobuf-compiler libprotobuf-dev    # Debian/Ubuntu (Dockerfile đã có sẵn)
```

Server phải bật HTTP/2. Trong [config/app.php](../../config/app.php) đã bật sẵn `Constant::OPTION_OPEN_HTTP2_PROTOCOL => true`.

Cấu hình nằm ở [config/grpc.php](../../config/grpc.php):

```dotenv
GRPC_ENABLED=true
GRPC_PROTOC=protoc           # đường dẫn tới protoc nếu không có trong PATH
GRPC_CLIENT_ADDRESS=127.0.0.1:9090
```

> Muốn encode/decode nhanh hơn thì cài thêm ext C: `pecl install protobuf`. Không cần sửa code.

## 2. Message

Message là class bình thường. Mỗi property public là một field.

```php
namespace Ryra\Modules\User\Grpc;

use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Attributes\Message;
use Ryra\Core\Grpc\Protobuf\Type;

#[Message(comment: 'Thông tin người dùng')]
final class User
{
    public function __construct(
        #[Field(1)] public int $id = 0,
        #[Field(2)] public string $name = '',
        #[Field(3)] public ?string $email = null,                                // optional string
        #[Field(4)] public UserStatus $status = UserStatus::Active,              // enum
        #[Field(5, type: Type::String)] public array $tags = [],                 // repeated string
        #[Field(6, type: Type::Int64, key: Type::String)] public array $stats = [], // map<string, int64>
        #[Field(7)] public ?Address $address = null,                             // message con
        #[Field(8, type: Address::class)] public array $addresses = [],          // repeated Address
        #[Field(9)] public ?\DateTimeImmutable $createdAt = null,                // google.protobuf.Timestamp
        #[Field(10, type: Type::Sint32)] public int $score = 0,                  // đổi kiểu số
    ) {}
}

enum UserStatus: int   // enum phải là int-backed
{
    case Active = 1;
    case Banned = 2;
}
```

Kiểu proto được suy ra từ kiểu PHP:

| PHP | Proto |
|-----|-------|
| `int` | `int64` (đổi bằng `type: Type::Int32`, `Sint64`, `Uint32`, `Fixed64`, ...) |
| `float` | `double` (hoặc `Type::Float`) |
| `bool` | `bool` |
| `string` | `string` (hoặc `Type::Bytes`) |
| `?string`, `?int`, ... | `optional ...` (phân biệt được "không gửi" với giá trị rỗng) |
| Class khác | message |
| Enum int-backed | enum. Nếu enum không có case `0`, framework tự thêm `XXX_UNSPECIFIED = 0`. |
| `DateTimeInterface`, `DateTimeImmutable`, Carbon | `google.protobuf.Timestamp` |
| `array` + `#[Field(type: X)]` | `repeated X` |
| `array` + `#[Field(type: V, key: K)]` | `map<K, V>` |

Các tuỳ chọn khác:

- `#[Field]` là không bắt buộc. Nếu bỏ, số field tự tăng theo thứ tự khai báo. **Nên luôn đặt số cố định**, vì đổi số là phá tương thích với client cũ.
- `#[Field(name: 'user_id')]` đổi tên field trong proto. Mặc định là snake_case của tên property.
- `#[Field(deprecated: true)]` và `#[Field(comment: '...')]`.
- `#[Ignore]` để bỏ qua một property.
- `#[Message(package: 'common.v1')]` đặt message vào package riêng. Nếu không khai báo, message thuộc package của service đầu tiên dùng nó.
- `#[Enum(prefix: false)]`: không thêm tên enum vào trước tên value.
- Property có thể là `readonly`. Lúc decode, framework không gọi constructor.

## 3. Service

```php
namespace Ryra\Modules\User\Grpc;

use Ryra\Core\Container\Inject;
use Ryra\Core\Grpc\Attributes\Rpc;
use Ryra\Core\Grpc\Attributes\Service;
use Ryra\Core\Grpc\Exception\GrpcException;
use Ryra\Core\Grpc\Server\ServerContext;

#[Service(package: 'user.v1')]
class UserService
{
    #[Inject]
    protected UserRepository $users;   // DI giống controller

    // unary
    public function getUser(GetUserRequest $request, ServerContext $context): User
    {
        $user = $this->users->find($request->id)
            ?? throw GrpcException::notFound('User not found');

        $context->setTrailer('x-cache', 'miss');
        return $user;
    }

    // server streaming: trả về Generator / iterable, khai báo kiểu message trả về
    #[Rpc(response: User::class)]
    public function listUsers(ListUsersRequest $request): \Generator
    {
        foreach ($this->users->paginate($request->page) as $user) {
            yield $user;
        }
    }

    // client streaming: tham số iterable, khai báo kiểu message nhận vào
    #[Rpc(request: User::class)]
    public function importUsers(iterable $users): ImportResult
    {
        $count = 0;
        foreach ($users as $user) {
            $this->users->save($user);
            $count++;
        }
        return new ImportResult($count);
    }

    // không tham số hoặc trả về void => google.protobuf.Empty
    public function ping(): void {}
}
```

Quy tắc:

- Mọi method public khai báo trong class đều là rpc. Dùng `#[Ignore]` để bỏ qua một method.
- Tên rpc mặc định là tên method viết hoa chữ cái đầu (`getUser` → `GetUser`). Đổi bằng `#[Rpc(name: 'FetchUser')]`.
- Tham số kiểu `ServerContext` là tuỳ chọn và đặt ở vị trí nào cũng được.
- `#[Service]` có thể đặt trên **interface**. Server implement interface đó, còn client chỉ cần interface để gọi.

Đăng ký service trong file module:

```php
// modules/user/user.module.php
return [
    'name' => 'User',
    'grpc_services' => [
        UserService::class,
    ],
];
```

Hoặc đăng ký toàn cục trong `config/grpc.php` → `services`.

### ServerContext

```php
$context->metadata('x-request-id');   // header client gửi lên
$context->bearerToken();              // từ header authorization
$context->timeRemaining();            // số giây còn lại theo grpc-timeout
$context->isExpired();
$context->setHeader('x-a', '1');      // response header
$context->setTrailer('x-b', '2');     // trailer
$context->set('user', $user);         // truyền dữ liệu từ interceptor xuống service
$context->fullMethod();               // /user.v1.UserService/GetUser
```

### Lỗi

```php
throw new GrpcException(Code::FAILED_PRECONDITION, 'Số dư không đủ', ['x-balance' => '0']);
throw GrpcException::invalidArgument('name is required');
throw GrpcException::unauthenticated();
```

Exception khác không bắt sẽ trả về `INTERNAL`. Khi `APP_DEBUG=true`, message lỗi sẽ chứa tên class và nội dung exception.

## 4. Sinh code & file .proto

```bash
php ryra grpc:generate        # sinh .proto + chạy protoc => storage/grpc (chạy lại mỗi khi sửa message/service)
php ryra grpc:proto           # xuất .proto sạch ra thư mục proto/ để gửi cho team khác (Go, Node, Java, ...)
php ryra grpc:proto --dry-run # chỉ in ra màn hình
php ryra grpc:list            # liệt kê các rpc
```

Mỗi package proto sinh ra một file, ví dụ `user.v1.proto`:

```proto
syntax = "proto3";

package user.v1;

import "google/protobuf/timestamp.proto";

service UserService {
  rpc GetUser(GetUserRequest) returns (User);
  rpc ListUsers(ListUsersRequest) returns (stream User);
}

message User {
  int64 id = 1;
  string name = 2;
  optional string email = 3;
  ...
}
```

Thêm option cho các ngôn ngữ khác qua `config/grpc.php` → `proto.options`, ví dụ `'go_package' => 'example.com/gen/userpb'`.

> Thư mục `storage/grpc` là build output, đã nằm trong `.gitignore`. Khi deploy, nhớ chạy `php ryra grpc:generate` trước khi start server.

## 5. Interceptor

```php
use Ryra\Core\Grpc\Server\Interceptor;

class AuthInterceptor implements Interceptor
{
    public function intercept(mixed $request, ServerContext $context, Closure $next): mixed
    {
        if ($context->service->package === 'grpc.health.v1') {
            return $next($request, $context);
        }
        $user = $this->jwt->verify($context->bearerToken() ?? '')
            ?? throw GrpcException::unauthenticated();
        $context->set('user', $user);

        return $next($request, $context);
    }
}
```

Khai báo trong `config/grpc.php` → `interceptors`. Các interceptor chạy theo thứ tự khai báo.

## 6. Client

Khai báo kết nối trong `config/grpc.php` → `clients`:

```php
'clients' => [
    'default' => ['address' => '127.0.0.1:9090', 'timeout' => 5.0],
    'payment' => ['address' => 'payment.internal:443', 'ssl' => true, 'metadata' => ['x-api-key' => '...']],
],
```

```php
use Ryra\Core\Grpc\Facade\Grpc;

// Gọi qua class/interface có #[Service] (khuyên dùng)
$users = Grpc::service(UserService::class);
$user = $users->getUser(new GetUserRequest(1));

foreach ($users->listUsers(new ListUsersRequest(page: 1)) as $user) { ... }

$result = $users->importUsers([$user1, $user2]);

$users->withMetadata(['authorization' => "Bearer $token"])
    ->withTimeout(2.0)
    ->getUser(new GetUserRequest(1));

// Gọi theo path
$user = Grpc::connection('default')->unary('/user.v1.UserService/GetUser', new GetUserRequest(1), User::class);
```

- Client chạy trên coroutine (`Swoole\Coroutine\Http2\Client`) và có pool kết nối (`max_connections`). Nhiều coroutine gọi song song được.
- Nếu gọi ngoài coroutine (ví dụ trong một command), client tự bọc lời gọi trong `Coroutine\run`.
- Khi server trả lỗi, client ném `GrpcException`, đọc mã lỗi qua `$e->status`.

## 7. Dùng file .proto có sẵn (proto-first, giống Hyperf)

Khi cần gọi hoặc implement service mà team khác đã định nghĩa bằng `.proto`:

```php
// config/grpc.php
'proto_paths' => [base_path('proto/vendor')],
```

`php ryra grpc:generate` sẽ compile luôn các file này. Class sinh ra theo `package` (hoặc `option php_namespace`) và được autoload sẵn. Có thể dùng các class đó trực tiếp trong service hoặc client, framework không map gì thêm:

```php
#[Service(package: 'payment.v1', name: 'PaymentService')]
class PaymentService
{
    public function charge(\Payment\V1\ChargeRequest $request): \Payment\V1\ChargeReply { ... }
}

$reply = Grpc::connection('payment')->unary('/payment.v1.PaymentService/Charge', $request, \Payment\V1\ChargeReply::class);
```

Service dùng class protoc sẽ không được xuất ra bằng `grpc:proto`, vì `.proto` gốc đã có sẵn.

## 8. Health check

Có sẵn service chuẩn `grpc.health.v1.Health/Check`, dùng được với Kubernetes probe và `grpc_health_probe`. Tắt bằng `'health' => false`.

```php
use Ryra\Core\Grpc\Health\HealthService;
use Ryra\Core\Grpc\Health\ServingStatus;

HealthService::setStatus('user.v1.UserService', ServingStatus::NOT_SERVING);
```

## 9. Test bằng grpcurl

```bash
grpcurl -plaintext -import-path storage/grpc/proto -proto example.v1.proto \
  -d '{"name":"Ryra"}' localhost:9090 example.v1.GreeterService/SayHello
```

Module `example` có sẵn một service mẫu ở [modules/example/src/Grpc](../../modules/example/src/Grpc).

## 10. Giới hạn hiện tại

- **Streaming được buffer**: Swoole 5.1 không hỗ trợ `write()` trên HTTP/2. Server streaming gom hết message rồi mới gửi một lần. Client streaming cũng chỉ xử lý sau khi nhận đủ request. Kết quả vẫn đúng chuẩn gRPC, nhưng không đẩy dữ liệu theo thời gian thực. Chưa hỗ trợ bidirectional streaming.
- Chưa có gRPC server reflection. Khi dùng grpcurl hoặc Postman, cần trỏ tới file `.proto`.
- Chưa hỗ trợ `oneof` và `uint64` lớn hơn `PHP_INT_MAX`.
