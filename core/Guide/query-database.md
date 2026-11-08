# Truy vấn Database

## Query Builder (qua Model)
```php
// Truy vấn cơ bản
$this->model()->find($id);
$this->model()->all();
$this->model()->findBy('email', 'test@example.com');

// Điều kiện where
$this->model()->alias('t')
    ->where('t.status', 'active')
    ->where('t.created_at', '>=', '2024-01-01')
    ->whereIn('t.type', ['a', 'b', 'c'])
    ->whereNull('t.deleted_at');

// Join
$this->model()->alias('t')
    ->join('bookings b', 't.id', '=', 'b.tour_id')
    ->leftJoin('customers c', 'b.customer_id', '=', 'c.id')
    ->select('t.*', 'b.status', 'c.name');

// Hàm tổng hợp
$this->model()->count();
$this->model()->sum('price');
$this->model()->avg('rating');
$this->model()->max('created_at');

// Sắp xếp & giới hạn
$this->model()->orderBy('created_at', 'desc');
$this->model()->limit(10)->offset(0);

// Phân trang
$this->model()->paginate($perPage);           // Phân trang đơn giản
$this->model()->paginate($dto->per_page);      // Lấy số bản ghi/trang từ DTO
```

## Truy vấn SQL thuần (Raw Query)
```php
use Ryra\Core\Database\Facades\DB;

// Select
$users = DB::select('SELECT * FROM users WHERE status = ?', ['active']);

// Insert / Update / Delete
DB::statement('UPDATE users SET status = ? WHERE id = ?', ['inactive', $id]);

// Lấy một giá trị đơn
$count = DB::scalar('SELECT COUNT(*) FROM users WHERE status = ?', ['active']);
```

## Transaction
```php
DB::transaction(function () {
    $user = DB::select('...');
    DB::statement('UPDATE ...');
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
- Dùng `$this->model()` trong các service kế thừa `BaseService`
- Dùng facade `DB::` cho truy vấn SQL thuần
- Luôn truyền tham số qua placeholder `?` khi viết SQL thuần, tránh SQL injection
- Phân trang trả về đối tượng `Paginator`, có các hàm `->items()`, `->total()`, `->lastPage()`, ...
