# Use Core Services

## Dependency Injection
```php
// Resolve from container
$service = App::service(SomeClass::class);

// Or via constructor injection (in controllers/services)
public function __construct(
    private SomeServiceInterface $service
) {}
```

## Snowflake ID
```php
$id = Snowflake::id(); // Generate unique distributed ID
```

## Hashing
```php
$hash = Hash::make($password);           // Hash password
$check = Hash::check($password, $hash);  // Verify password
```

## Event System
```php
// Dispatch event
Event::dispatch(new UserRegistered($user));

// Listen (via EventServiceProvider)
Event::listen(UserRegistered::class, SendWelcomeEmailListener::class);
```

## Caching (Redis)
```php
Cache::set('key', $value, 3600);    // Store for 1 hour
Cache::get('key');                   // Retrieve
Cache::delete('key');                // Delete
Cache::remember('key', 3600, fn() => db query); // Cache with fallback
```

## Redis
```php
$redis = Redis::connection();
$redis->set('key', 'value');
$redis->get('key');
```

## Logging
```php
Log::info('User created', ['id' => $id]);
Log::error('Something failed', ['exception' => $e]);
Log::warning('Deprecated method used');
```

## Mail
```php
Mail::send($email); // Send email (configured via config/mail.php)
```

## Queue / Messenger
```php
// Dispatch async job
Messenger::dispatch(new SendEmailJob($data));

// Or with delay
Messenger::dispatch(new SendEmailJob($data))->delay(3600);
```

## Context (Current user/business)
```php
Context::get('user.user_id');       // Current authenticated user ID
Context::get('user.business_id');   // Current business ID
Context::get('user.role');          // Current user role
```

## Key points
- All core services are accessible via facades
- Constructor injection is preferred in controllers/services
- Context provides access to the current authenticated user and business
