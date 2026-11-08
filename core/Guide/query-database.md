# Query Database

## Query Builder (via Model)
```php
// Basic queries
$this->model()->find($id);
$this->model()->all();
$this->model()->findBy('email', 'test@example.com');

// Where clauses
$this->model()->alias('t')
    ->where('t.status', 'active')
    ->where('t.created_at', '>=', '2024-01-01')
    ->whereIn('t.type', ['a', 'b', 'c'])
    ->whereNull('t.deleted_at');

// Joins
$this->model()->alias('t')
    ->join('bookings b', 't.id', '=', 'b.tour_id')
    ->leftJoin('customers c', 'b.customer_id', '=', 'c.id')
    ->select('t.*', 'b.status', 'c.name');

// Aggregates
$this->model()->count();
$this->model()->sum('price');
$this->model()->avg('rating');
$this->model()->max('created_at');

// Ordering & Limit
$this->model()->orderBy('created_at', 'desc');
$this->model()->limit(10)->offset(0);

// Pagination
$this->model()->paginate($perPage);           // Simple pagination
$this->model()->paginate($dto->per_page);      // With DTO input
```

## Raw Queries
```php
use Ryra\Core\Database\Facades\DB;

// Select
$users = DB::select('SELECT * FROM users WHERE status = ?', ['active']);

// Insert / Update / Delete
DB::statement('UPDATE users SET status = ? WHERE id = ?', ['inactive', $id]);

// Scalar
$count = DB::scalar('SELECT COUNT(*) FROM users WHERE status = ?', ['active']);
```

## Transactions
```php
DB::transaction(function () {
    $user = DB::select('...');
    DB::statement('UPDATE ...');
    // Auto-committed on success, rolled back on exception
});

// Manual transaction control
DB::beginTransaction();
try {
    // ...
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    throw $e;
}
```

## Key points
- Use `$this->model()` in services extending `BaseService`
- Use `DB::` facade for raw queries
- Always use parameter binding (`?` placeholders) for raw queries
- Pagination returns a `Paginator` instance with `->items()`, `->total()`, `->lastPage()`, etc.
