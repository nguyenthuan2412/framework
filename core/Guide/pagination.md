# Phân trang (Pagination)

Có hai kiểu phân trang:

| Kiểu | Hàm | Khi nào dùng |
|------|-----|--------------|
| Theo số trang | `paginate()` | Danh sách cần biết tổng số trang, có nút 1, 2, 3, ... |
| Theo con trỏ | `cursorPaginate()` | Danh sách rất lớn, cuộn vô hạn, feed. Nhanh hơn vì không cần `COUNT(*)` và không dùng `OFFSET` |

## 1. Phân trang theo số trang

```php
$books = Book::query()
    ->where('status', 'published')
    ->orderBy('created_at', 'desc')
    ->paginate(20);          // 20 bản ghi mỗi trang, số trang lấy từ ?page=

return Response::json($books);
```

Kết quả JSON:

```json
{
  "current_page": 2,
  "data": [ ... ],
  "first_page_url": "http://localhost:9090/api/books?page=1",
  "from": 20,
  "last_page": 5,
  "last_page_url": "http://localhost:9090/api/books?page=5",
  "next_page_url": "http://localhost:9090/api/books?page=3",
  "path": "http://localhost:9090/api/books",
  "per_page": 20,
  "prev_page_url": "http://localhost:9090/api/books?page=1",
  "to": 40,
  "total": 95
}
```

### Nên validate số trang qua DTO

`paginate()` đọc thẳng `?page=` mà **không kiểm tra**. Giá trị như `?page=abc` hay `?page=0` sẽ gây lỗi 500. Vì vậy hãy validate trước rồi truyền số trang vào tường minh:

```php
class ListBookDTO extends ValidatedDTO
{
    #[IsOptional] #[IsInteger(transform: true)] #[Min(1)]
    public $page;

    #[IsOptional] #[IsInteger(transform: true)] #[Min(1)] #[Max(100)]
    public $per_page;
}

#[Get(uri: '/')]
public function index(ListBookDTO $dto): JsonResponse
{
    $books = Book::query()->paginate($dto->per_page ?? 20, $dto->page ?? 1);
    return Response::json($books);
}
```

### Làm việc với đối tượng `Paginator`

```php
$books->getData();         // Collection các bản ghi của trang hiện tại
$books->getTotal();        // tổng số bản ghi
$books->getTotalPage();    // tổng số trang
$books->getCurrentPage();
$books->getPerPage();
$books->getFrom();         // vị trí bắt đầu (offset)
$books->getTo();
$books->toArray();         // mảng như JSON ở trên

foreach ($books as $book) { /* duyệt trực tiếp */ }

// Gọi hàm của Collection để biến đổi data, các thông tin phân trang vẫn giữ nguyên
$books->map(fn ($b) => ['id' => $b->id, 'title' => $b->title]);
```

## 2. Phân trang theo con trỏ

```php
$posts = Post::query()
    ->orderBy('id', 'desc')
    ->cursorPaginate(20);    // con trỏ đọc từ ?cursor=

return Response::json($posts);
```

```json
{
  "data": [ ... ],
  "path": "http://localhost:9090/api/posts",
  "per_page": 20,
  "next_cursor": "eyJpZCI6MTAwLCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9",
  "next_page_url": "http://localhost:9090/api/posts?cursor=eyJpZCI6...",
  "prev_cursor": null,
  "prev_page_url": null
}
```

Client chỉ cần gọi tiếp `next_page_url` cho tới khi nó bằng `null`.

- Query **phải có `orderBy`** theo một cột có giá trị duy nhất và có index (thường là `id`).
- Không nhảy được tới "trang 5". Chỉ đi được tới trang kế tiếp hoặc trang trước.

## 3. Dùng với Query Builder

`paginate()` và `cursorPaginate()` cũng dùng được trên query builder không gắn model:

```php
use Ryra\Core\Database\Facades\DB;

DB::table('orders')->where('user_id', $id)->paginate(10, $page);
```
