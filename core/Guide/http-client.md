# HTTP Client: gọi API bên ngoài

Facade `Http` dùng HTTP client coroutine của Swoole. Khi chờ phản hồi, request **không chặn** server, các request khác vẫn được xử lý bình thường.

```php
use Ryra\Core\HttpClient\Facade\Http;
```

> Client này chỉ chạy **bên trong coroutine**. Trong controller, middleware hay listener thì luôn đúng điều kiện này. Nếu gọi từ một lệnh console, hãy bọc code trong `\Swoole\Coroutine\run(function () { ... })`.

---

## 1. Gửi request

```php
// GET: $params được gắn vào query string (?page=1&limit=10)
$res = Http::get('https://api.example.com/books', ['page' => 1, 'limit' => 10]);

// POST dạng form (application/x-www-form-urlencoded), là mặc định
$res = Http::post('https://api.example.com/books', ['title' => 'Dế Mèn']);

// POST dạng JSON: PHẢI gọi asJson()
$res = Http::asJson()->post('https://api.example.com/books', ['title' => 'Dế Mèn']);

Http::asJson()->put($url, $data);
Http::asJson()->patch($url, $data);
Http::delete($url);
Http::head($url);
```

> Mỗi lần gọi `Http::...` sẽ tạo một client mới, nên các cấu hình như header hay token không bị lẫn giữa các lần gọi.

## 2. Đọc response

```php
$res->status();                // 200
$res->ok();                    // status == 200
$res->successful();            // 2xx
$res->failed();                // 4xx hoặc 5xx
$res->clientError();           // 4xx
$res->serverError();           // 5xx
$res->notFound();              // 404 (còn có unauthorized(), forbidden(), tooManyRequests(), ...)

$res->body();                  // chuỗi body gốc
$res->json();                  // mảng (json_decode)
$res->json('data.items');      // lấy theo dấu chấm
$res->json('data.total', 0);   // kèm giá trị mặc định
$res->object();                // stdClass
$res->collect('data.items');   // Collection
$res['data'];                  // truy cập như mảng, tương đương json('data')

$res->header('Content-Type');
$res->headers();
```

### Ném exception khi lỗi

```php
use Ryra\Core\HttpClient\Exception\RequestException;

$data = Http::get($url)->throw()->json();            // 4xx/5xx thì ném RequestException
$data = Http::get($url)->throwIf($strict)->json();
```

## 3. Header, xác thực, timeout

```php
Http::withHeaders(['X-Tenant' => 'abc'])
    ->withHeader('X-Request-Id', $id)
    ->withToken($token)                 // Authorization: Bearer <token>
    ->withBasicAuth('user', 'pass')
    ->withApiKey($key)
    ->acceptJson()                      // Accept: application/json
    ->timeout(10)                       // tổng thời gian tối đa (giây)
    ->connectTimeout(3)                 // thời gian kết nối tối đa (giây)
    ->get($url);
```

### Base URL

```php
$client = Http::baseUrl('https://api.example.com/v1')->withToken($token);

$client->get('/books');        // https://api.example.com/v1/books
$client->get('/authors');
```

### Thêm query cho mọi method

```php
Http::withParams(['lang' => 'vi'])->asJson()->post('/search', ['q' => 'php']);
// POST /search?lang=vi, body JSON {"q":"php"}
```

## 4. Upload file

```php
Http::attach('avatar', '/path/to/photo.jpg', 'image/jpeg', 'photo.jpg')
    ->post('https://api.example.com/upload', ['user_id' => 1]);
```

`attach()` tự chuyển request sang `multipart/form-data`. File phải tồn tại trên đĩa, nếu không sẽ ném `RuntimeException`.

## 5. Thử lại khi lỗi

```php
Http::retry(3, 500)->get($url);
// Gửi tối đa 3 lần (tính cả lần đầu), mỗi lần cách nhau 500ms.
// Chỉ thử lại khi response bị lỗi (4xx/5xx).
```

## 6. Redirect

Mặc định client **không** tự đi theo redirect. Muốn bật:

```php
Http::allowRedirect()->get($url);              // tối đa 5 lần chuyển hướng
Http::allowRedirect(max: 10)->get($url);

// Gọi callback mỗi khi bị chuyển hướng (tự bật allowRedirect nếu chưa bật)
Http::onRedirect(function (\Ryra\Core\HttpClient\Response $response, string $location) {
    Log::info("Redirect {$response->status()} -> $location");
})->get($url);
```

## 7. Stream: nhận dữ liệu theo từng phần

Dùng cho Server-Sent Events (ví dụ API AI trả token liên tục) hoặc tải file lớn mà không giữ toàn bộ trong bộ nhớ:

```php
Http::withToken($key)->asJson()->stream(
    'https://api.example.com/stream',
    function (string $chunk) {
        // xử lý từng mảnh dữ liệu
    },
    ['prompt' => 'Xin chào'],
    'POST',
);
```

- Khi stream và không tự đặt `timeout()`, giới hạn thời gian được bỏ.
- Request dạng stream **không** tự thử lại.

## 8. HTTP/2

```php
Http::withVersion(2.0)->get($url);   // hỗ trợ 1.0, 1.1, 2.0
```
