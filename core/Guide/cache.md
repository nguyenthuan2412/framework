# Cache & Redis

## 1. Cấu hình Cache

File [config/cache.php](../../config/cache.php). Chọn driver bằng biến `CACHE_STORE`:

| Driver | Lưu ở đâu | Ghi chú |
|--------|-----------|---------|
| `database` (mặc định) | Bảng `cache` trong DB | Bảng **tự được tạo** ở lần dùng đầu tiên (MySQL / PostgreSQL / SQLite) |
| `file` | `storage/cache/data/` | Phù hợp khi chạy một server |
| `redis` | Redis, connection `cache` trong `config/database.php` | Nhanh nhất, dùng được khi chạy nhiều server |

```dotenv
CACHE_STORE=redis
CACHE_PREFIX=myapp-
```

Mọi key đều được gắn thêm tiền tố `CACHE_PREFIX` để tránh trùng giữa các ứng dụng.

## 2. Sử dụng

```php
use Ryra\Core\Cache\Facade\Cache;

Cache::set('stats', $data);                 // lưu vĩnh viễn
Cache::set('stats', $data, 3600);           // lưu 3600 giây
Cache::set('stats', $data, new \DateInterval('PT10M')); // lưu 10 phút

Cache::get('stats');                        // null nếu không có
Cache::get('stats', []);                    // giá trị mặc định
Cache::has('stats');
Cache::delete('stats');
Cache::clear();                             // xoá toàn bộ cache
```

### `remember`: có thì lấy, chưa có thì tính rồi lưu

```php
$books = Cache::remember('books.top', 600, function () {
    return Book::query()->orderBy('views', 'desc')->limit(10)->get();
});
```

### Nhiều key cùng lúc

```php
Cache::setMultiple(['a' => 1, 'b' => 2], 60);
Cache::getMultiple(['a', 'b'], null);       // ['a' => 1, 'b' => 2]
Cache::deleteMultiple(['a', 'b']);
```

### Dùng driver khác driver mặc định

```php
Cache::driver('file')->set('key', 'value', 60);
```

Giá trị được serialize trước khi lưu, nên có thể lưu mảng và object. Với object, class phải serialize được: không chứa closure hay resource.

---

## 3. Redis

Cấu hình trong [config/database.php](../../config/database.php), mục `redis`. Có sẵn 4 connection: `default`, `cache`, `session`, `queue`. Mỗi connection dùng một DB index riêng.

```dotenv
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_CLIENT=phpredis      # hoặc predis
```

Cần cài extension `redis` (khi dùng `phpredis`) hoặc chạy `composer require predis/predis` (khi dùng `predis`).

```php
use Ryra\Core\Redis\Facade\Redis;

Redis::set('key', 'value');
Redis::setex('otp:0912345678', 300, '123456');   // hết hạn sau 300 giây
Redis::get('key');
Redis::incr('counter');
Redis::del('key');

// Dùng connection khác connection mặc định
Redis::connection('cache')->get('key');
```

Facade `Redis` hỗ trợ hầu hết lệnh của Redis (string, hash, list, set, sorted set, ...). Tên hàm trùng với tên lệnh.

### Connection pool

Connection Redis được lấy từ **pool**. Mỗi request (coroutine) mượn một connection và **tự trả lại** khi request kết thúc, bạn không cần tự đóng. Kích thước pool chỉnh bằng `REDIS_POOL_MIN` và `REDIS_POOL_MAX`.

### Pub/Sub

Lệnh `subscribe` chặn connection, nên phải dùng connection riêng:

```php
Redis::subscriber()->subscribe(['channel'], function (string $channel, string $message) {
    // xử lý message
});
```
