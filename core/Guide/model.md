# Tạo Model

## Cấu trúc cơ bản
```php
<?php

namespace Ryra\Modules\{ModuleName}\Models;

use Ryra\Core\Database\Model\Model;

class Customer extends Model
{
    protected string $table = 'customers';

    protected string $primaryKey = 'id';
    
    protected bool $incrementing = false;
    
    protected array $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'price' => 'float',
    ];

    protected array $hidden = [
        'password',
        'remember_token',
    ];
}
```

## Quan hệ (Relationships)
```php
// BelongsTo (thuộc về)
public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');
}

// HasMany (có nhiều)
public function bookings(): HasMany
{
    return $this->hasMany(Booking::class, 'customer_id');
}

// HasOne (có một)
public function profile(): HasOne
{
    return $this->hasOne(Profile::class, 'customer_id');
}
```

## Accessor & Mutator
```php
use Ryra\Core\Database\Model\Attributes\Mutator;
use Ryra\Core\Database\Model\Attributes\Accessor;

// Mutator (biến đổi giá trị trước khi lưu)
#[Mutator]
protected function hashPassword(string $value): string
{
    return Hash::make($value);
}

// Accessor (biến đổi giá trị sau khi đọc ra)
#[Accessor]
protected function fullName(): string
{
    return $this->first_name . ' ' . $this->last_name;
}
```

## Xoá mềm (Soft Deletes)
```php
use Ryra\Core\Database\Model\Traits\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    // ...
}
```

## Ghi nhớ
- Kế thừa `Ryra\Core\Database\Model\Model`
- ID dạng Snowflake không tự tăng, nên đặt `$incrementing = false`
- Hỗ trợ các attribute `#[Mutator]`, `#[Event]`, `#[Accessor]`
- Xoá mềm bằng trait `SoftDeletes`
