# Tạo Module

Cách nhanh nhất để tạo module mới là dùng lệnh có sẵn:

```bash
php ryra gen:module {ten_module}
```

Ví dụ tạo module `user`:

```bash
php ryra gen:module user
```

Lệnh này tự tạo cấu trúc thư mục, file khai báo module (manifest) và service provider.

Bạn cũng có thể tạo module thủ công theo các bước dưới đây.

---

## Bước 1: Tạo cấu trúc thư mục
```
modules/{ten_module}/
├── {ten_module}.module.php
├── src/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── DTOs/
│   ├── Enums/
│   ├── Providers/
│   ├── Middlewares/
│   └── Repositories/
└── views/
```

## Bước 2: Tạo file khai báo module
Tạo file `modules/{ten_module}/{ten_module}.module.php`:

```php
<?php

return [
    'name' => 'Module Name',
    'controllers' => [
        // Đăng ký các class controller tại đây
    ],
    'providers' => [
        // Đăng ký các service provider tại đây
    ],
];
```

## Bước 3: Đăng ký controller và provider
- Controller được nạp tự động từ mảng `controllers`
- Service provider được khởi động (boot) theo đúng thứ tự khai báo
- Module được nạp tự động qua `autoload_modules.php`
