# Đa ngôn ngữ (Translation)

## 1. Cấu hình

File [config/translation.php](../../config/translation.php):

```php
'locale' => env('APP_LOCALE', 'vi'),          // ngôn ngữ mặc định
'fallback' => env('APP_FALLBACK_LOCALE', 'en'), // dùng khi không tìm thấy bản dịch
'available_locales' => ['vi', 'en'],          // các ngôn ngữ cho phép
'paths' => [base_path('lang')],               // thư mục chứa file dịch
```

Muốn hỗ trợ thêm ngôn ngữ, thêm mã ngôn ngữ vào `available_locales`.

## 2. File dịch

Cấu trúc: `lang/{locale}/{nhóm}.php`. Mỗi file trả về một mảng.

```
lang/
├── vi/
│   ├── messages.php
│   └── validation.php
└── en/
    ├── messages.php
    └── validation.php
```

```php
// lang/vi/messages.php
return [
    'welcome' => 'Xin chào, :name!',
    'cart' => [
        'empty' => 'Giỏ hàng trống',
    ],
    'items' => '{0} Không có sản phẩm|{1} Một sản phẩm|[2,*] :count sản phẩm',
];
```

## 3. Dịch

```php
t('messages.welcome', ['name' => 'An']);   // "Xin chào, An!"
t('messages.cart.empty');                   // dấu chấm để đi sâu vào mảng
t('messages.welcome', ['name' => 'An'], 'en'); // ép dùng ngôn ngữ khác
t('messages.not_exist');                    // không tìm thấy: trả về chính key "messages.not_exist"
```

Phần đầu của key (trước dấu chấm đầu tiên) là **tên file**, phần còn lại là đường dẫn trong mảng.

### Thay tham số

Hỗ trợ cả `:name` và `{name}`:

| Trong file dịch | Kết quả với `['name' => 'an']` |
|-----------------|--------------------------------|
| `:name` | `an` |
| `:Name` | `An` (viết hoa chữ đầu) |
| `:NAME` | `AN` (viết hoa toàn bộ) |
| `{name}` | `an` |

### Số nhiều

Khi truyền `count` và bản dịch có ký tự `|`, framework tự chọn dạng phù hợp:

```php
t('messages.items', ['count' => 0]);   // "Không có sản phẩm"
t('messages.items', ['count' => 1]);   // "Một sản phẩm"
t('messages.items', ['count' => 5]);   // "5 sản phẩm"
t('messages.items', ['count' => $cart->items]); // truyền mảng/Countable thì tự đếm
```

### Trong Twig

```twig
{{ t('messages.welcome', {name: user.name}) }}
{{ 'messages.cart.empty'|t }}
```

## 4. Đổi ngôn ngữ cho request hiện tại

```php
use Ryra\Core\App;

App::setLocale('en');     // ném lỗi nếu 'en' không có trong available_locales
App::getLocale();         // 'en'

translator()->getAvailableLocales();
translator()->isValidLocale('ja');   // false
```

Ngôn ngữ được lưu **riêng cho từng request (coroutine)**, nên đổi ngôn ngữ ở request này không ảnh hưởng request khác. Cách thường dùng là viết một middleware đọc header `Accept-Language` rồi gọi `App::setLocale()`:

```php
public function handle(Request $request, Closure $next): Response
{
    $locale = $request->getPreferredLanguage(translator()->getAvailableLocales());
    if ($locale) {
        App::setLocale($locale);
    }
    return $next($request);
}
```
