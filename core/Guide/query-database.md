# Truy vấn Database

## Lưu ý quan trọng: thứ tự tham số của `where`

> Trong Ryra, **toán tử so sánh nằm ở tham số cuối**:
>
> ```php
> where($column, $value, $operator = '=')
> ```
>
> Viết theo kiểu Laravel `where('price', '>', 100)` **không báo lỗi**, nhưng sinh ra câu SQL sai. Cách viết đúng là `where('price', 100, '>')`.
>
> `join()` cũng tương tự: `join($table, $first, $second, $operator = '=')`.

## Query Builder (qua Model)
```php
use Ryra\Modules\Tour\Models\Tour;

// Truy vấn cơ bản
Tour::query()->find($id);                            // ?Tour
Tour::query()->get();                                // Collection mọi bản ghi
Tour::query()->where('slug', $slug)->first();        // bản ghi đầu tiên hoặc null

// Gọi tĩnh trực tiếp trên model cũng được (tự tạo query)
Tour::where('status', 'active')->get();

// Điều kiện where
Tour::query()->alias('t')
    ->where('t.status', 'active')                    // t.status = 'active'
    ->where('t.created_at', '2024-01-01', '>=')      // t.created_at >= '2024-01-01'
    ->where('t.price', 0, '>')                       // t.price > 0
    ->whereIn('t.type', ['a', 'b', 'c'])
    ->whereNull('t.deleted_at')
    ->get();

// Nhóm điều kiện: (a OR b)
Tour::query()
    ->where(function ($q) {
        $q->where('status', 'active')->orWhere('featured', 1);
    })
    ->get();

// Join: join($bảng, $cột1, $cột2, $toán_tử = '=')
Tour::query()->alias('t')
    ->join('bookings b', 't.id', 'b.tour_id')
    ->leftJoin('customers c', 'b.customer_id', 'c.id')
    ->select('t.*', 'b.status', 'c.name')
    ->get();

// Hàm tổng hợp
Tour::query()->count();
Tour::query()->sum('price');
Tour::query()->avg('rating');
Tour::query()->max('created_at');

// Lấy một cột
Tour::query()->pluck('name', 'id');                  // Collection [id => name]
Tour::query()->where('id', $id)->value('name');

// Sắp xếp & giới hạn
Tour::query()->orderBy('created_at', 'desc');
Tour::query()->limit(10, 20);                        // LIMIT 10 OFFSET 20
Tour::query()->limit(10)->offset(20);

// Phân trang (xem pagination.md)
Tour::query()->paginate($perPage, $page);
Tour::query()->cursorPaginate($perPage);
```

## Query Builder không gắn model
```php
use Ryra\Core\Database\DB;

DB::table('orders')->where('user_id', $id)->orderBy('id', 'desc')->get();
DB::table('orders')->insertGetId(['user_id' => $id, 'total' => 100000]);
DB::table('orders')->where('id', $orderId)->update(['status' => 'paid']);
DB::table('orders')->where('id', $orderId)->delete();
DB::table('orders')->where('status', 'paid')->sum('total');

// Biểu thức SQL bên trong query builder
DB::table('orders')->selectRaw('DATE(created_at) AS day, SUM(total) AS revenue')->groupBy('day')->get();
DB::table('orders')->where('total', DB::raw('discount * 2'), '>')->get();   // total > discount * 2
```

> `DB::select(...)` là hàm **chọn cột** của query builder, tương đương `SELECT col1, col2`. Nó không phải hàm chạy câu SQL.

## Truy vấn SQL thuần (Raw Query)
```php
use Ryra\Core\Database\DB;

$conn = DB::connection();            // connection mặc định
$conn = DB::connection('mysql');     // connection theo tên trong config/database.php

// Select: trả về mảng các dòng
$users = $conn->select('SELECT * FROM users WHERE status = ?', ['active']);
$user  = $conn->selectOne('SELECT * FROM users WHERE id = ?', [$id]);

// Insert / Update / Delete: trả về số dòng bị ảnh hưởng
$conn->insert('INSERT INTO logs (message) VALUES (?)', ['hello']);
$conn->update('UPDATE users SET status = ? WHERE id = ?', ['inactive', $id]);
$conn->delete('DELETE FROM sessions WHERE last_activity < ?', [$time]);

// Câu lệnh khác (DDL, ...)
$conn->statement('TRUNCATE TABLE cache');
```

## Transaction
```php
DB::transaction(function () use ($orderId) {
    DB::table('orders')->where('id', $orderId)->update(['status' => 'paid']);
    DB::table('payments')->insert(['order_id' => $orderId, 'amount' => 100000]);
    // Tự commit khi thành công, tự rollback khi có exception
});

// Tự điều khiển transaction
DB::beginTransaction();
try {
    // ...
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    throw $e;
}
```

## Ghi nhớ
- Bắt đầu truy vấn model bằng `Model::query()`
- Toán tử của `where()` nằm ở **tham số thứ ba**: `where('price', 100, '>')`
- Dùng `DB::table()` cho query builder không gắn model, `DB::connection()->select()` cho SQL thuần
- Luôn truyền tham số qua placeholder `?` khi viết SQL thuần, tránh SQL injection
- Phân trang trả về đối tượng `Paginator`, có các hàm `->getData()`, `->getTotal()`, `->getTotalPage()`, ... (xem [pagination.md](pagination.md))
