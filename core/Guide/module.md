# Create a Module

You can quickly generate a new module using the built-in command:

```bash
php ryra gen:module {module_name}
```

For example, to create a `user` module:

```bash
php ryra gen:module user
```

This command will automatically scaffold the directory structure, module manifest, and service provider for you.

Alternatively, you can create a module manually by following the steps below.

---

## Step 1: Create directory structure
```
modules/{module_name}/
├── {module_name}.module.php
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

## Step 2: Create module manifest
Create `modules/{module_name}/{module_name}.module.php`:

```php
<?php

return [
    'name' => 'Module Name',
    'controllers' => [
        // Register controller classes here
    ],
    'providers' => [
        // Register service providers here
    ],
];
```

## Step 3: Register controllers & providers
- Controllers are auto-discovered from the `controllers` array
- Service providers are booted in the order listed
- Module is automatically loaded via `autoload_modules.php`
