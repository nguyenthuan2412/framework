# Request: cách dùng trong Controller

## Tổng quan

[`Request`](../Http/Request.php) là class chính để lấy dữ liệu từ HTTP request trong Ryra. Nó bọc request gốc của Swoole và cung cấp các hàm tiện lợi để đọc:

- **Tham số query** (`?key=value`)
- **Dữ liệu body** (JSON, form-data, x-www-form-urlencoded)
- **Headers**
- **Cookies**
- **File upload**
- **Biến server**
- **Session**

Chỉ cần khai báo tham số kiểu `Request` trong method của controller, framework sẽ tự inject.

---

## 1. Inject Request vào Controller

### 1.1. Cách cơ bản

Khai báo [`Request`](../Http/Request.php) làm tham số của method, framework tự truyền vào:

```php
use Ryra\Core\Http\Request;
use Ryra\Core\Http\JsonResponse;
use Ryra\Core\Http\Response;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Route\Attributes\Group;
use Ryra\Core\Route\Attributes\Post;

#[Group(prefix: 'api/books')]
class BookController
{
    #[Get(uri: '/', summary: 'List books')]
    public function index(Request $request): JsonResponse
    {
        // $request đã sẵn sàng để dùng
        $keyword = $request->input('keyword', '');
        $page    = $request->integer('page', 1);

        return Response::json([...]);
    }
}
```

### 1.2. Kết hợp với tham số route

Request có thể dùng chung với tham số route:

```php
#[Put(uri: '{id:\d+}', summary: 'Update book')]
public function update(int $id, Request $request): JsonResponse
{
    $title = $request->input('title');
    // ...
}
```

### 1.3. Kết hợp với DTO (validate)

Khi dùng DTO, bạn vẫn có thể inject thêm Request nếu cần đọc dữ liệu không nằm trong DTO:

```php
#[Post(uri: '/', summary: 'Create book')]
public function store(CreateBookDTO $dto, Request $request): JsonResponse
{
    // $dto chứa dữ liệu đã validate
    // $request dùng để lấy thêm dữ liệu phụ
    $ip = $request->ip();
    // ...
}
```

---

## 2. Đọc dữ liệu đầu vào

### 2.1. `input()`: lấy giá trị bất kỳ (query + body)

Tìm trong body trước, không có thì tìm trong query string:

```php
$name    = $request->input('name');          // 'John' hoặc null
$name    = $request->input('name', 'Guest'); // 'John' hoặc 'Guest' (mặc định)
$nested  = $request->input('user.address.city'); // Dùng dấu chấm để lấy dữ liệu lồng nhau
```

### 2.2. `query()`: chỉ đọc từ query string

```php
$page = $request->query('page');     // ?page=2 → '2'
$sort = $request->query('sort', 'asc');
```

### 2.3. `all()`: toàn bộ dữ liệu (query + body + file)

```php
$data = $request->all();
// [
//     'name' => 'John',
//     'email' => 'john@example.com',
//     'avatar' => UploadedFile,
// ]
```

### 2.4. `has()`: kiểm tra key có tồn tại

```php
if ($request->has('email')) {
    // có email trong request (body, query hoặc file)
}

if ($request->has(['name', 'email'])) {
    // có đủ tất cả các key
}
```

### 2.5. `__get()`: đọc như thuộc tính

```php
$name = $request->name; // Tương đương $request->input('name')
$email = $request->email;
```

---

## 3. Các hàm ép kiểu

### 3.1. `string()`: trả về [`Stringable`](../Support/Str.php)

```php
$name = $request->string('name', '');
// $name là đối tượng Stringable, có thể gọi nối tiếp các method
$slug = $request->string('title')->slug()->lower();
```

### 3.2. `integer()`: ép về int

```php
$age  = $request->integer('age', 0);     // int
$page = $request->integer('page', 1);
```

### 3.3. `float()`: ép về float

```php
$price = $request->float('price', 0.0);
```

### 3.4. `boolean()`: ép về bool

```php
$active = $request->boolean('is_active', false);
// "1", "true", "on", "yes" → true
// "0", "false", "off", "no", "" → false
```

### 3.5. `date()`: đọc ngày tháng

```php
use DateTimeInterface;

$startDate = $request->date('start_date');
// Trả về DateTimeInterface hoặc null

$customFormat = $request->date('start_date', 'Y-m-d');
// Đọc theo định dạng cụ thể
```

### 3.6. `enum()`: lấy Backed Enum từ input

```php
use App\Enums\Status;

$status = $request->enum('status', Status::class);
// ?Status: trả về enum hoặc null
```

---

## 4. Đọc header

### 4.1. `header()`: lấy giá trị header

```php
$token     = $request->header('Authorization');
$userAgent = $request->header('User-Agent', 'Unknown');
$lang      = $request->header('Accept-Language');
```

### 4.2. `hasHeader()`: kiểm tra header có tồn tại

```php
if ($request->hasHeader('X-Request-Id')) {
    // ...
}
```

### 4.3. `bearerToken()`: lấy Bearer token

```php
$token = $request->bearerToken();
// Authorization: Bearer abc123 → 'abc123'
// Trả về null nếu không có
```

### 4.4. `userAgent()`: lấy nhanh User-Agent

```php
$ua = $request->userAgent(); // Tương đương $request->header('User-Agent')
```

### 4.5. `isJson()` / `wantsJson()`: kiểm tra Content-Type / Accept

```php
if ($request->isJson()) {
    // Content-Type là application/json
}

if ($request->wantsJson()) {
    // Header Accept ưu tiên JSON
}
```

### 4.6. `accepts()`: kiểm tra header Accept

```php
if ($request->accepts('application/json')) {
    // Client chấp nhận JSON
}

if ($request->accepts(['application/json', 'text/html'])) {
    // Client chấp nhận một trong các định dạng
}
```

---

## 5. Cookies

```php
$sessionId = $request->cookie('session_id');
$hasCookie = $request->hasCookie('remember_token');
```

---

## 6. File upload

### 6.1. `file()`: lấy file upload

```php
use Ryra\Core\Http\File\UploadedFile;

$avatar = $request->file('avatar'); // ?UploadedFile

if ($avatar) {
    $originalName = $avatar->getClientOriginalName();
    $extension    = $avatar->getClientOriginalExtension();
    $size         = $avatar->getSize();
    $mime         = $avatar->getMimeType();
    $path         = $avatar->getPathname();
}
```

### 6.2. Kiểm tra có file hay không

```php
if ($request->has('avatar')) {
    // Request có file 'avatar'
}
```

---

## 7. HTTP method & URL

### 7.1. `method()` / `isMethod()`

```php
$method = $request->method(); // 'GET', 'POST', ...

if ($request->isMethod('post')) {
    // Là request POST
}

if ($request->isMethodSafe()) {
    // GET, HEAD, OPTIONS, TRACE
}

if ($request->isMethodCacheable()) {
    // GET, HEAD
}
```

### 7.2. Các hàm về URL

```php
$uri     = $request->uri();          // /api/books?page=1 → '/api/books'
$url     = $request->url();          // https://example.com/api/books
$fullUrl = $request->fullUrl();      // https://example.com/api/books?page=1

// Tạo URL mới kèm tham số query
$newUrl  = $request->fullUrlWithQuery(['page' => 2]);
// https://example.com/api/books?page=2

// Kiểm tra URL theo mẫu
if ($request->is('api/books/*')) { ... }
if ($request->fullUrlIs('*page=*')) { ... }
```

### 7.3. Schema (HTTP/HTTPS)

```php
$schema = $request->schema(); // 'https' hoặc 'http'
$secure = $request->isSecure();
```

### 7.4. Host & Port

```php
$host = $request->getHost(); // 'example.com'
$port = $request->port();    // 443, 80, ...
```

---

## 8. IP của client

```php
$ip  = $request->ip();         // '192.168.1.1'
$ips = $request->ips();        // ['192.168.1.1', ...]: chuỗi IP khi đi qua proxy
$ip  = $request->getClientIp();
```

---

## 9. Session

```php
$session = $request->session(); // ?Session

if ($request->hasSession()) {
    $userId = $session->get('user_id');
}
```

---

## 10. Thương lượng nội dung (Content Negotiation)

### 10.1. Ngôn ngữ

```php
$languages = $request->getLanguages();
// ['vi', 'en', ...] theo thứ tự ưu tiên trong Accept-Language

$preferred = $request->getPreferredLanguage(['vi', 'en', 'ja']);
// 'vi': ngôn ngữ khớp nhất trong danh sách hỗ trợ
```

### 10.2. Kiểu nội dung

```php
$types = $request->getAcceptableContentTypes();
// ['application/json', 'text/html', ...]

$format = $request->getRequestFormat(); // 'html' (mặc định)
```

---

## 11. AJAX & PJAX

```php
if ($request->ajax()) {
    // X-Requested-With: XMLHttpRequest
}

if ($request->pjax()) {
    // X-PJAX header
}
```

---

## 12. ETags & Cache

```php
$etags = $request->getETags(); // Lấy từ header If-None-Match

if ($request->isNoCache()) {
    // Cache-Control: no-cache hoặc Pragma: no-cache
}
```

---

## 13. Truy cập request gốc của Swoole

Khi cần dùng trực tiếp đối tượng request của Swoole:

```php
$swooleRequest = $request->getSwooleRequest();
$fd = $request->fd; // File descriptor (kết nối TCP)
```

---

## 14. Route hiện tại

```php
$route = $request->route(); // ?Route
// Hoặc
$route = $request->getRoute();
```

---

## 15. Ví dụ đầy đủ

```php
<?php

namespace Ryra\Modules\Book\Controllers;

use Ryra\Core\Http\JsonResponse;
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;
use Ryra\Core\Route\Attributes\Delete;
use Ryra\Core\Route\Attributes\Get;
use Ryra\Core\Route\Attributes\Group;
use Ryra\Core\Route\Attributes\Post;
use Ryra\Core\Route\Attributes\Put;
use Ryra\Modules\Book\DTOs\UpsertBookDTO;
use Ryra\Modules\Book\Models\Book;
use Ryra\Modules\Book\Services\BookServiceInterface;
use Ryra\Modules\Common\Middlewares\AuthMiddleware;

#[Group(
    prefix: 'api/books',
    middlewares: [AuthMiddleware::class],
    description: 'Book management'
)]
class BookController
{
    public function __construct(
        protected BookServiceInterface $bookService,
    ) {}

    #[Get(uri: '/', summary: 'List books')]
    public function index(Request $request): JsonResponse
    {
        // Đọc tham số query kèm ép kiểu
        $keyword = $request->string('keyword', '');
        $page    = $request->integer('page', 1);
        $perPage = min(50, $request->integer('per_page', 10));
        $status  = $request->enum('status', BookStatus::class);

        // Kiểm tra client có muốn nhận JSON không
        if ($request->wantsJson()) {
            $books = $this->bookService->paginate($keyword, $page, $perPage, $status);
            return Response::json([
                'message' => 'Success',
                ...$books->toArray(),
            ]);
        }

        // Không phải JSON thì trả lỗi định dạng
        return Response::json(['message' => 'Unsupported format'], 406);
    }

    #[Post(uri: '/', summary: 'Create new book')]
    public function store(UpsertBookDTO $dto, Request $request): JsonResponse
    {
        // DTO đã được tự validate, request dùng để lấy thêm thông tin
        $createdBy = $request->input('created_by', auth()->id());

        $book = $this->bookService->create($dto, $createdBy);

        return Response::json([
            'message' => 'Book created successfully',
            'data'    => $book,
        ], 201);
    }

    #[Get(uri: '{book:\d+}', summary: 'Book details')]
    public function show(Book $book): JsonResponse
    {
        return Response::json([
            'message' => 'Success',
            'data'    => $book->toArray(),
        ]);
    }

    #[Put(uri: '{book:\d+}', summary: 'Update book')]
    public function update(Book $book, UpsertBookDTO $dto): JsonResponse
    {
        $updated = $this->bookService->update($book->id, $dto);

        return Response::json([
            'message' => 'Book updated successfully',
            'data'    => $updated,
        ]);
    }

    #[Delete(uri: '{book:\d+}', summary: 'Delete book')]
    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return Response::json([
            'message' => 'Book deleted successfully',
        ]);
    }

    #[Post(uri: '{book:\d+}/cover', summary: 'Upload cover image')]
    public function uploadCover(Book $book, Request $request): JsonResponse
    {
        // Xử lý file upload
        $file = $request->file('cover');

        if (!$file) {
            return Response::json([
                'message' => 'No file uploaded',
            ], 400);
        }

        // Kiểm tra loại file
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return Response::json([
                'message' => 'Invalid file type. Allowed: JPEG, PNG, WebP',
            ], 422);
        }

        // Upload và lưu
        $path = $file->move('books/covers'); // lưu vào storage/public/books/covers, trả về đường dẫn tương đối
        $book->cover_url = $path;
        $book->save();

        return Response::json([
            'message' => 'Cover uploaded successfully',
            'data'    => ['url' => $path],
        ]);
    }

    #[Get(uri: 'stats', summary: 'Book statistics')]
    public function stats(Request $request): JsonResponse
    {
        // Dùng nhiều hàm của Request trong cùng một action
        $filters = [
            'from'  => $request->date('from'),
            'to'    => $request->date('to'),
            'genre' => $request->input('genre'),
        ];

        // Lấy thống kê
        $stats = $this->bookService->getStats($filters);

        return Response::json([
            'message'    => 'Stats retrieved',
            'data'       => $stats,
            'request_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
```

---

## 16. Bảng so sánh nhanh

| Hàm | Nguồn dữ liệu | Kiểu trả về | Ghi chú |
|--------|-------------|-------------|-------|
| `input(key, default)` | Body → Query | `mixed` | Tìm trong body trước, không có thì tìm trong query |
| `query(key)` | Query string | `mixed` | Chỉ `?key=value` |
| `all()` | Body + Query + Files | `array` | Gộp tất cả |
| `string(key, default)` | Body → Query | `Stringable` | Đối tượng chuỗi, gọi nối tiếp được |
| `integer(key, default)` | Body → Query | `int` | Ép về int |
| `float(key, default)` | Body → Query | `float` | Ép về float |
| `boolean(key, default)` | Body → Query | `bool` | Ép về bool |
| `date(key, format?)` | Body → Query | `?DateTimeInterface` | Đọc ngày tháng |
| `enum(key, class)` | Body → Query | `?BackedEnum` | Backed Enum |
| `header(key, default)` | Headers | `mixed` | HTTP headers |
| `bearerToken()` | Authorization header | `?string` | Phần token sau "Bearer " |
| `cookie(key, default)` | Cookies | `mixed` | Cookie của request |
| `file(key)` | File upload | `?UploadedFile` | File upload |
| `has(key)` | Body + Query + Files | `bool` | Kiểm tra tồn tại |
| `method()` | Server | `string` | HTTP method |
| `uri()` | Server | `string` | URI path |
| `url()` | Server | `string` | URL đầy đủ (không kèm query) |
| `fullUrl()` | Server | `string` | URL đầy đủ (kèm query) |
| `ip()` | Server/Proxy | `string` | IP của client |
| `schema()` | Server/Proxy | `string` | `https` hoặc `http` |
| `isSecure()` | Server/Proxy | `bool` | Có dùng SSL/TLS không? |
| `ajax()` | Headers | `bool` | Có phải XMLHttpRequest không? |
| `wantsJson()` | Accept header | `bool` | Client muốn nhận JSON? |
| `isJson()` | Content-Type | `bool` | Body của request là JSON? |
| `session()` | Session | `?Session` | Session instance |
| `route()` | Route | `?Route` | Route hiện tại |
