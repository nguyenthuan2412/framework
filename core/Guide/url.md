# Sinh URL

## 1. URL từ đường dẫn

```php
url('/books');                          // http://localhost:9090/books
url('/books', ['page' => 2]);           // http://localhost:9090/books?page=2
secure_url('/checkout');                // https://localhost:9090/checkout
```

Gốc URL lấy từ `APP_URL`. Hàm trả về đối tượng `Url`. Khi dùng như chuỗi (nối chuỗi, đưa vào JSON, ...), nó tự chuyển thành chuỗi. Muốn lấy chuỗi tường minh thì gọi `->toString()` hoặc `(string)`.

## 2. URL từ tên route

Tên route = `name` của `#[Group]` + `name` của route:

```php
#[Group(prefix: 'api/books', name: 'books.')]
class BookController
{
    #[Get(uri: '{slug}', name: 'show')]       // tên đầy đủ: books.show
    public function show(string $slug) { /* ... */ }
}
```

```php
route('books.show', ['slug' => 'de-men']);                  // http://localhost:9090/api/books/de-men
route('books.show', ['slug' => 'de-men', 'ref' => 'home']); // ...?ref=home (tham số thừa thành query)
route('books.show', ['slug' => 'de-men'], absolute: false); // /api/books/de-men
route('not.exist');                                          // null
```

> **Giới hạn hiện tại:** `route()` chưa thay được tham số có regex như `{id:\d+}`. Ví dụ `route('books.find', ['id' => 5])` với `uri: '{id:\d+}'` sẽ sinh ra `/api/books/{id:\d+}?id=5`. Với route cần sinh URL bằng `route()`, hãy khai báo tham số không kèm regex (`{id}`), rồi kiểm tra kiểu dữ liệu bằng type hint `int $id` trong method.

Trong Twig:

```twig
<a href="{{ route('books.show', {slug: book.slug}) }}">{{ book.title }}</a>
```

## 3. Đối tượng `Url`

```php
use Ryra\Core\Url\Url;

$url = Url::parse('https://shop.vn/books?page=1#top');

$url->scheme();        // 'https'
$url->host();          // 'shop.vn'
$url->path();          // '/books'
$url->getQueryParam('page');   // '1'
$url->fragment();      // 'top'
$url->segments();      // ['books']

// Các hàm with* trả về ĐỐI TƯỢNG MỚI, đối tượng gốc không đổi
$next = $url->withQueryParam('page', 2)->withoutQueryParam('sort')->withFragment(null);

Url::current();        // URL của request hiện tại
Url::base();           // URL gốc của ứng dụng (APP_URL)
Url::to('/books', ['q' => 'php']);
Url::isValidUrl('abc');        // false
```

## 4. URL có chữ ký (signed URL)

Dùng cho link không cần đăng nhập nhưng không được phép sửa, ví dụ link xác nhận email hay link tải file. Chữ ký được tạo bằng `APP_KEY`.

```php
// Link vĩnh viễn
$link = signed_url('email.verify', ['user' => $user->id]);

// Link hết hạn sau 30 phút
$link = signed_url('email.verify', ['user' => $user->id], now()->addMinutes(30));
$link = signed_url('email.verify', ['user' => $user->id], 1800);   // tính bằng giây

// Với URL bất kỳ
$link = url('/download/report.pdf')->temporarySignedUrl(600);
```

Kiểm tra trong controller:

```php
use Ryra\Core\Url\Url;
use Ryra\Core\Http\Exception\AccessDeniedHttpException;

#[Get(uri: 'verify/{user}', name: 'verify')]
public function verify(int $user): JsonResponse
{
    if (!Url::current()->hasValidSignature()) {
        throw new AccessDeniedHttpException('Link không hợp lệ hoặc đã hết hạn');
    }
    // ...
}
```

> Chữ ký được kiểm tra trên **toàn bộ URL**, gồm cả `http`/`https` và domain. Nếu app chạy sau reverse proxy (nginx, load balancer), hãy khai báo IP của proxy trong `trusted_proxies` ở [config/app.php](../../config/app.php). Nếu không, URL mà server nhìn thấy sẽ khác URL đã ký, và chữ ký sẽ bị báo sai.

> Bắt buộc phải đặt `APP_KEY` trong `.env`, và không được đổi giá trị này sau khi đã gửi link cho người dùng. Đổi key thì mọi link cũ sẽ thành không hợp lệ.
