# Example Module

Module mẫu đơn giản, không dùng database.

```
modules/example/
├── example.module.php
├── views/welcome.twig
└── src/
    ├── Controllers/ExampleController.php
    ├── DTOs/GreetDTO.php
    ├── Providers/ExampleServiceProvider.php
    └── Services/GreetingService.php
```

| Method | URI | Mô tả |
|--------|-----|-------|
| GET | `/example` | Trang HTML (Twig) |
| GET | `/example/hello/{name}` | Chào theo tham số route |
| POST | `/example/greet` | Chào theo body `{"name": "..."}`, có validate |
