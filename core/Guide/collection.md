# Collection, Arr, Str & Date

Các class tiện ích để xử lý mảng, chuỗi và ngày giờ. API giống Laravel, nên nếu đã quen Laravel bạn có thể dùng ngay.

---

## 1. Collection

Kết quả `get()` của query luôn là `Collection`. Bạn cũng có thể tự tạo bằng `collect()`:

```php
$books = collect([
    ['id' => 1, 'title' => 'Dế Mèn', 'price' => 50000, 'genre' => 'thieu-nhi'],
    ['id' => 2, 'title' => 'Tắt Đèn', 'price' => 70000, 'genre' => 'van-hoc'],
    ['id' => 3, 'title' => 'Số Đỏ',   'price' => 65000, 'genre' => 'van-hoc'],
]);

$books->count();                              // 3
$books->pluck('title');                       // ['Dế Mèn', 'Tắt Đèn', 'Số Đỏ']
$books->pluck('title', 'id');                 // [1 => 'Dế Mèn', 2 => 'Tắt Đèn', ...]
$books->sum('price');                         // 185000
$books->where('genre', 'van-hoc');            // lọc theo giá trị
$books->whereIn('id', [1, 3]);
$books->filter(fn ($b) => $b['price'] > 60000);
$books->map(fn ($b) => $b['title']);
$books->first();                              // phần tử đầu
$books->first(fn ($b) => $b['price'] > 60000);
$books->sortBy('price');
$books->sortByDesc('price');
$books->groupBy('genre');                     // ['thieu-nhi' => [...], 'van-hoc' => [...]]
$books->keyBy('id');                          // [1 => [...], 2 => [...], ...]
$books->chunk(2);                             // chia thành các nhóm 2 phần tử
$books->unique('genre');
$books->contains('title', 'Số Đỏ');
$books->isEmpty();
$books->values();                             // đánh lại chỉ số 0, 1, 2, ...
$books->toArray();
```

Các hàm như `map`, `filter`, `sortBy`, ... trả về **collection mới**, collection gốc giữ nguyên. Vì vậy có thể gọi nối tiếp:

```php
$titles = $books
    ->where('genre', 'van-hoc')
    ->sortByDesc('price')
    ->pluck('title')
    ->values()
    ->all();
```

Xem toàn bộ hàm trong [Collection.php](../Collection/Collection.php) và [EnumeratesValues.php](../Collection/Traits/EnumeratesValues.php).

## 2. Arr: xử lý mảng thường

```php
use Ryra\Core\Collection\Arr;

$data = ['user' => ['name' => 'An', 'address' => ['city' => 'Hà Nội']]];

Arr::get($data, 'user.address.city');         // 'Hà Nội' (dùng dấu chấm)
Arr::get($data, 'user.phone', 'không có');    // giá trị mặc định
Arr::has($data, 'user.name');                 // true
Arr::set($data, 'user.phone', '0912');        // gán theo dấu chấm
Arr::forget($data, 'user.address');
Arr::only($data['user'], ['name']);
Arr::except($data['user'], ['phone']);
Arr::pluck($rows, 'id');
Arr::first($rows, fn ($r) => $r['active']);
Arr::wrap('a');                               // ['a'], nếu đã là mảng thì giữ nguyên
```

## 3. Str: xử lý chuỗi

```php
use Ryra\Core\Support\Str;

Str::slug('Dế Mèn Phiêu Lưu Ký');    // 'de-men-phieu-luu-ky'
Str::camel('user_name');             // 'userName'
Str::snake('UserName');              // 'user_name'
Str::studly('user_name');            // 'UserName'
Str::kebab('UserName');              // 'user-name'
Str::title('xin chào');              // 'Xin Chào'
Str::lower('ABC'); Str::upper('abc');
Str::limit('Một đoạn văn rất dài', 10);           // 'Một đoạn v...'
Str::contains('hello world', 'world');            // true
Str::startsWith('0912345678', '09');
Str::endsWith('photo.jpg', ['.jpg', '.png']);
Str::after('user@example.com', '@');              // 'example.com'
Str::before('user@example.com', '@');             // 'user'
Str::replace('cũ', 'mới', $text);
Str::mask('0912345678', '*', 3, 4);               // '091****678'
Str::random(32);                                  // chuỗi ngẫu nhiên an toàn
Str::uuid();
Str::ulid();
```

Viết nối tiếp bằng `str()`:

```php
str('  Dế Mèn Phiêu Lưu Ký  ')->trim()->slug()->limit(10);
```

## 4. Date: ngày giờ

`Ryra\Core\Date\Date` kế thừa [Carbon](https://carbon.nesbot.com/docs/), nên dùng được mọi hàm của Carbon.

```php
use Ryra\Core\Date\Date;

now();                                   // thời điểm hiện tại
now()->addDays(7);
now()->subMonth()->startOfMonth();
Date::parse('2026-11-09 08:00');
Date::createFromFormat('d/m/Y', '09/11/2026');

$date->format('d/m/Y H:i');
$date->diffForHumans();                  // '3 giờ trước' (theo ngôn ngữ hiện tại)
$date->isPast();
$date->isWeekend();
$date->diffInDays($other);
```

Múi giờ mặc định lấy từ `APP_TIMEZONE`.
