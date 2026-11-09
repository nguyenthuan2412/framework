# Response: trả kết quả từ Controller

Mọi method trong controller phải trả về một đối tượng [`Response`](../Http/Response.php) (hoặc class con của nó). Cách nhanh nhất là dùng các hàm tĩnh của `Response`.

```php
use Ryra\Core\Http\Response;
use Ryra\Core\Http\JsonResponse;
```

---

## 1. Các kiểu response

### JSON

```php
return Response::json(['data' => $users]);                 // 200
return Response::json(['message' => 'Created'], 201);      // kèm status code
return Response::json($data, 200, ['X-Total' => '100']);   // kèm header
```

`$data` có thể là mảng, model, collection, `Paginator` hoặc bất kỳ đối tượng nào implement `JsonSerializable`.

### HTML từ view Twig

```php
return Response::view('welcome.twig', ['title' => 'Trang chủ']);
```

- Đường dẫn **không** bắt đầu bằng `@` sẽ được tìm trong thư mục `views/` của **module chứa controller hiện tại**.
- Muốn lấy view của module khác, dùng tiền tố `@{module}`, ví dụ `Response::view('@user/profile.twig')`.

### HTML / text thuần

```php
return Response::html('<h1>Xin chào</h1>');
return Response::text('OK');
```

### Redirect

```php
return Response::redirect('/login');                 // 302
return Response::redirect('/new-url', 301);          // chuyển hướng vĩnh viễn
return Response::redirect()->withRoute('user.show', ['id' => 1]); // theo tên route
return Response::redirect()->back();                 // quay lại trang trước (cần session)
```

Khi redirect có thể gửi kèm dữ liệu flash (cần middleware `StartSession`):

```php
return Response::redirect()->back()
    ->with('success', 'Đã lưu')       // flash một giá trị
    ->withErrors(['email' => 'Sai'])  // flash lỗi
    ->withInput();                    // flash lại dữ liệu người dùng đã nhập
```

### Tải file

```php
// File có sẵn trên đĩa
return Response::download(storage_path('public/report.pdf'), 'bao-cao.pdf');

// Nội dung sinh ra lúc chạy
return Response::streamDownload(fn () => yield $csv, 'export.csv');
```

### Stream (gửi dần từng phần)

```php
return Response::stream(function () {
    foreach ($rows as $row) {
        yield json_encode($row) . "\n";
    }
}, 200, ['Content-Type' => 'application/x-ndjson']);
```

Callback phải là **generator** (dùng `yield`). Mỗi lần `yield`, một đoạn dữ liệu được gửi ngay cho client.

---

## 2. Header, cookie, status

Các hàm `with*` trả về chính response nên có thể gọi nối tiếp:

```php
return Response::json($data)
    ->setStatusCode(202)
    ->withHeader('X-Request-Id', $id)
    ->withHeaders(['Cache-Control' => 'no-store'])
    ->withCookie('theme', 'dark', expire: now()->addDays(30))
    ->withoutCookie('old_cookie');
```

Tham số của `withCookie()`: `name, value, expire, path = '/', domain, secure, httpOnly = true, raw = false, sameSite = 'lax', partitioned = false`.

---

## 3. Trả lỗi HTTP bằng exception

Thay vì tự tạo response lỗi, bạn có thể `throw` exception có sẵn trong [`Ryra\Core\Http\Exception`](../Http/Exception/). Middleware `HandleException` sẽ bắt lại và trả về:

- JSON `{"message": "..."}` nếu client gửi header `Accept: application/json`
- Trang lỗi HTML trong trường hợp còn lại

```php
use Ryra\Core\Http\Exception\NotFoundHttpException;
use Ryra\Core\Http\Exception\AccessDeniedHttpException;

if (!$book) {
    throw new NotFoundHttpException('Không tìm thấy sách');
}
```

Một số exception hay dùng:

| Exception | Status |
|-----------|--------|
| `BadRequestHttpException` | 400 |
| `UnauthorizedHttpException` | 401 |
| `AccessDeniedHttpException` | 403 |
| `NotFoundHttpException` | 404 |
| `MethodNotAllowedHttpException` | 405 |
| `ConflictHttpException` | 409 |
| `UnprocessableEntityHttpException` | 422 |
| `TooManyRequestsHttpException` | 429 |
| `ServiceUnavailableHttpException` | 503 |

Exception khác (không phải HTTP exception):
- `APP_DEBUG=true`: hiển thị chi tiết lỗi (JSON hoặc trang HTML)
- `APP_DEBUG=false`: trả lỗi 500 chung chung, chi tiết được ghi vào log

## 4. Lỗi validate DTO

Khi DTO validate thất bại, framework trả về status **422**:

```json
{
  "message": "...",
  "errors": { "email": ["..."] }
}
```

> Client gọi API nên luôn gửi header `Accept: application/json`. Nếu thiếu header này và request không có session, framework sẽ cố redirect về trang trước thay vì trả JSON.

Muốn đổi format lỗi cho một DTO, ghi đè hàm tĩnh `failedValidation()`:

```php
use Ryra\Core\Http\Request;
use Ryra\Core\Http\Response;
use Ryra\Core\ValidatedDTO\ValidationException;

class StoreBookDTO extends ValidatedDTO
{
    public static function failedValidation(Request $request, ValidationException $th): Response
    {
        return Response::json([
            'success' => false,
            'errors' => $th->getErrors(),
        ], 422);
    }
}
```
