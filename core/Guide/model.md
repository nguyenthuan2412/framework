# Create a Model

## Basic structure
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

## Relationships
```php
// BelongsTo
public function user(): BelongsTo
{
    return $this->belongsTo(User::class, 'user_id');
}

// HasMany
public function bookings(): HasMany
{
    return $this->hasMany(Booking::class, 'customer_id');
}

// HasOne
public function profile(): HasOne
{
    return $this->hasOne(Profile::class, 'customer_id');
}
```

## Attributes & Mutators
```php
use Ryra\Core\Database\Model\Attributes\Mutator;
use Ryra\Core\Database\Model\Attributes\Accessor;

// Mutator (modifies value before saving)
#[Mutator]
protected function hashPassword(string $value): string
{
    return Hash::make($value);
}

// Accessor (modifies value after retrieving)
#[Accessor]
protected function fullName(): string
{
    return $this->first_name . ' ' . $this->last_name;
}
```

## Soft Deletes
```php
use Ryra\Core\Database\Model\Traits\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    // ...
}
```

## Key points
- Extends `Ryra\Core\Database\Model\Model`
- Snowflake IDs are non-incrementing (`$incrementing = false`)
- Supports `#[Mutator]`, `#[Event]`, `#[Accessor]` attributes
- Soft deletes via `SoftDeletes` trait
