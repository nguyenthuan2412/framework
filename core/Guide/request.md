# Request — Usage Guide in Controller

## Overview

[`Request`](core/Http/Request.php) is the central class for retrieving data from HTTP requests in Ryra. It wraps the underlying Swoole request and provides a convenient API to read:

- **Query parameters** (`?key=value`)
- **Body parameters** (JSON, form-data, x-www-form-urlencoded)
- **Headers**
- **Cookies**
- **File uploads**
- **Server variables**
- **Session**

The Request is automatically injected into controller methods when declared as a type-hinted parameter.

---

## 1. Injecting Request into Controller

### 1.1. Basic Form

Declare [`Request`](core/Http/Request.php) as a method parameter — the framework injects it automatically:

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
        // $request is ready to use
        $keyword = $request->input('keyword', '');
        $page    = $request->integer('page', 1);

        return Response::json([...]);
    }
}
```

### 1.2. Combining with Route Parameters

Request can coexist with route parameters:

```php
#[Put(uri: '{id:\d+}', summary: 'Update book')]
public function update(int $id, Request $request): JsonResponse
{
    $title = $request->input('title');
    // ...
}
```

### 1.3. Combining with DTO (Validation)

When using DTO, the request can still be injected separately if you need access to additional data not included in the DTO:

```php
#[Post(uri: '/', summary: 'Create book')]
public function store(CreateBookDTO $dto, Request $request): JsonResponse
{
    // $dto contains validated data
    // $request is used to retrieve additional auxiliary data
    $ip = $request->ip();
    // ...
}
```

---

## 2. Reading Input Parameters

### 2.1. `input()` — Get any value (query + body)

Searches the request body first, then falls back to the query string:

```php
$name    = $request->input('name');          // 'John' or null
$name    = $request->input('name', 'Guest'); // 'John' or 'Guest' (default)
$nested  = $request->input('user.address.city'); // Dot notation for nested
```

### 2.2. `query()` — Read from query string only

```php
$page = $request->query('page');     // ?page=2 → '2'
$sort = $request->query('sort', 'asc');
```

### 2.3. `all()` — All input (query + body + files)

```php
$data = $request->all();
// [
//     'name' => 'John',
//     'email' => 'john@example.com',
//     'avatar' => UploadedFile,
// ]
```

### 2.4. `has()` — Check if key exists

```php
if ($request->has('email')) {
    // email exists in request (body, query, or files)
}

if ($request->has(['name', 'email'])) {
    // All keys exist
}
```

### 2.5. `__get()` — Magic property access

```php
$name = $request->name; // Equivalent to $request->input('name')
$email = $request->email;
```

---

## 3. Type Casting Helpers

### 3.1. `string()` — Returns [`Stringable`](core/Support/Str.php)

```php
$name = $request->string('name', '');
// $name is a Stringable object, methods can be chained
$slug = $request->string('title')->slug()->lower();
```

### 3.2. `integer()` — Cast to int

```php
$age  = $request->integer('age', 0);     // int
$page = $request->integer('page', 1);
```

### 3.3. `float()` — Cast to float

```php
$price = $request->float('price', 0.0);
```

### 3.4. `boolean()` — Cast to bool

```php
$active = $request->boolean('is_active', false);
// "1", "true", "on", "yes" → true
// "0", "false", "off", "no", "" → false
```

### 3.5. `date()` — Parse dates

```php
use DateTimeInterface;

$startDate = $request->date('start_date');
// Returns DateTimeInterface or null

$customFormat = $request->date('start_date', 'Y-m-d');
// Parse with specific format
```

### 3.6. `enum()` — Backed Enum from input

```php
use App\Enums\Status;

$status = $request->enum('status', Status::class);
// ?Status — returns enum instance or null
```

---

## 4. Reading Headers

### 4.1. `header()` — Get header value

```php
$token     = $request->header('Authorization');
$userAgent = $request->header('User-Agent', 'Unknown');
$lang      = $request->header('Accept-Language');
```

### 4.2. `hasHeader()` — Check header existence

```php
if ($request->hasHeader('X-Request-Id')) {
    // ...
}
```

### 4.3. `bearerToken()` — Get Bearer token

```php
$token = $request->bearerToken();
// Authorization: Bearer abc123 → 'abc123'
// Returns null if not present
```

### 4.4. `userAgent()` — User-Agent shortcut

```php
$ua = $request->userAgent(); // Equivalent to $request->header('User-Agent')
```

### 4.5. `isJson()` / `wantsJson()` — Check content type / accept

```php
if ($request->isJson()) {
    // Content-Type is application/json
}

if ($request->wantsJson()) {
    // Accept header prefers JSON
}
```

### 4.6. `accepts()` — Check Accept header

```php
if ($request->accepts('application/json')) {
    // Client accepts JSON
}

if ($request->accepts(['application/json', 'text/html'])) {
    // Client accepts one of the formats
}
```

---

## 5. Cookies

```php
$sessionId = $request->cookie('session_id');
$hasCookie = $request->hasCookie('remember_token');
```

---

## 6. File Uploads

### 6.1. `file()` — Get uploaded file

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

### 6.2. Check if file exists

```php
if ($request->has('avatar')) {
    // File 'avatar' exists in the request
}
```

---

## 7. HTTP Method & URL

### 7.1. `method()` / `isMethod()`

```php
$method = $request->method(); // 'GET', 'POST', ...

if ($request->isMethod('post')) {
    // Is a POST request
}

if ($request->isMethodSafe()) {
    // GET, HEAD, OPTIONS, TRACE
}

if ($request->isMethodCacheable()) {
    // GET, HEAD
}
```

### 7.2. URL Methods

```php
$uri     = $request->uri();          // /api/books?page=1 → '/api/books'
$url     = $request->url();          // https://example.com/api/books
$fullUrl = $request->fullUrl();      // https://example.com/api/books?page=1

// Create new URL with query parameters
$newUrl  = $request->fullUrlWithQuery(['page' => 2]);
// https://example.com/api/books?page=2

// Check URL pattern
if ($request->is('api/books/*')) { ... }
if ($request->fullUrlIs('*page=*')) { ... }
```

### 7.3. Schema (HTTP/HTTPS)

```php
$schema = $request->schema(); // 'https' or 'http'
$secure = $request->isSecure();
```

### 7.4. Host & Port

```php
$host = $request->getHost(); // 'example.com'
$port = $request->port();    // 443, 80, ...
```

---

## 8. Client IP

```php
$ip  = $request->ip();         // '192.168.1.1'
$ips = $request->ips();        // ['192.168.1.1', ...] — IP chain if behind proxy
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

## 10. Content Negotiation

### 10.1. Language

```php
$languages = $request->getLanguages();
// ['vi', 'en', ...] in priority order from Accept-Language

$preferred = $request->getPreferredLanguage(['vi', 'en', 'ja']);
// 'vi' — the best matching language from the supported list
```

### 10.2. Content Types

```php
$types = $request->getAcceptableContentTypes();
// ['application/json', 'text/html', ...]

$format = $request->getRequestFormat(); // 'html' (default)
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
$etags = $request->getETags(); // From If-None-Match header

if ($request->isNoCache()) {
    // Cache-Control: no-cache or Pragma: no-cache
}
```

---

## 13. Accessing the Underlying Swoole Request

When you need to access the Swoole request object:

```php
$swooleRequest = $request->getSwooleRequest();
$fd = $request->fd; // File descriptor (TCP connection)
```

---

## 14. Current Route

```php
$route = $request->route(); // ?Route
// Or
$route = $request->getRoute();
```

---

## 15. Complete Example

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
        // Read query parameters with type casting
        $keyword = $request->string('keyword', '');
        $page    = $request->integer('page', 1);
        $perPage = min(50, $request->integer('per_page', 10));
        $status  = $request->enum('status', BookStatus::class);

        // Check if client wants JSON
        if ($request->wantsJson()) {
            $books = $this->bookService->paginate($keyword, $page, $perPage, $status);
            return Response::json([
                'message' => 'Success',
                ...$books->toArray(),
            ]);
        }

        // Return HTML if not JSON
        return Response::json(['message' => 'Unsupported format'], 406);
    }

    #[Post(uri: '/', summary: 'Create new book')]
    public function store(UpsertBookDTO $dto, Request $request): JsonResponse
    {
        // DTO is validated automatically, request is used to get additional info
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
        // Handle file upload
        $file = $request->file('cover');

        if (!$file) {
            return Response::json([
                'message' => 'No file uploaded',
            ], 400);
        }

        // Validate file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return Response::json([
                'message' => 'Invalid file type. Allowed: JPEG, PNG, WebP',
            ], 422);
        }

        // Upload and save
        $path = $file->store('books/covers');
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
        // Use multiple Request methods in a single action
        $filters = [
            'from'  => $request->date('from'),
            'to'    => $request->date('to'),
            'genre' => $request->input('genre'),
        ];

        // Get client info
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

## 16. Quick Method Comparison

| Method | Data Source | Return Type | Notes |
|--------|-------------|-------------|-------|
| `input(key, default)` | Body → Query | `mixed` | Searches body first, falls back to query |
| `query(key)` | Query string | `mixed` | Only `?key=value` |
| `all()` | Body + Query + Files | `array` | Merges all |
| `string(key, default)` | Body → Query | `Stringable` | Chainable string object |
| `integer(key, default)` | Body → Query | `int` | Casts to int |
| `float(key, default)` | Body → Query | `float` | Casts to float |
| `boolean(key, default)` | Body → Query | `bool` | Casts to bool |
| `date(key, format?)` | Body → Query | `?DateTimeInterface` | Parses dates |
| `enum(key, class)` | Body → Query | `?BackedEnum` | Backed Enum |
| `header(key, default)` | Headers | `mixed` | HTTP headers |
| `bearerToken()` | Authorization header | `?string` | Token after "Bearer " |
| `cookie(key, default)` | Cookies | `mixed` | Request cookies |
| `file(key)` | Uploaded files | `?UploadedFile` | File upload |
| `has(key)` | Body + Query + Files | `bool` | Checks existence |
| `method()` | Server | `string` | HTTP method |
| `uri()` | Server | `string` | URI path |
| `url()` | Server | `string` | Full URL (without query) |
| `fullUrl()` | Server | `string` | Full URL (with query) |
| `ip()` | Server/Proxy | `string` | Client IP |
| `schema()` | Server/Proxy | `string` | `https` or `http` |
| `isSecure()` | Server/Proxy | `bool` | SSL/TLS? |
| `ajax()` | Headers | `bool` | XMLHttpRequest? |
| `wantsJson()` | Accept header | `bool` | Client wants JSON? |
| `isJson()` | Content-Type | `bool` | Request body is JSON? |
| `session()` | Session | `?Session` | Session instance |
| `route()` | Route | `?Route` | Current route |
