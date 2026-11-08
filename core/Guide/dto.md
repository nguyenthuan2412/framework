# Create a DTO (Data Transfer Object)

## Overview

DTOs in Ryra Framework serve two purposes:
1. **Data transfer** — Carry structured data between layers (Controller → Service, etc.)
2. **Validation** — Auto-validate incoming request data using PHP 8 attributes

There are two base classes:
- [`BaseDTO`](core/BaseDTO/BaseDTO.php) — Simple DTO without validation, for internal data transfer
- [`ValidatedDTO`](core/ValidatedDTO/ValidatedDTO.php) — DTO with attribute-based validation rules, for request validation

---

## 1. Basic Structure

```php
<?php

namespace Ryra\Modules\{ModuleName}\DTOs;

use Ryra\Core\ValidatedDTO\ValidatedDTO;
use Ryra\Core\ValidatedDTO\Rules\IsRequired;
use Ryra\Core\ValidatedDTO\Rules\IsString;
use Ryra\Core\ValidatedDTO\Rules\IsEmail;

class StoreCustomerDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    public $name;

    #[IsRequired]
    #[IsEmail]
    public $email;
}
```

### Key points:
- Extends [`ValidatedDTO`](core/ValidatedDTO/ValidatedDTO.php)
- Properties are **public** with no type hints — validation rules handle type checking
- Each property can have **multiple rule attributes** stacked
- Validation runs when constructed with `$validate = true` or via [`fromArray($data, true)`](core/ValidatedDTO/ValidatedDTO.php:187)
- Access values via `$dto->property`

---

## 2. All Available Rules

### 2.1 Presence
| Rule | Params | Description |
|------|--------|-------------|
| [`IsRequired`](core/ValidatedDTO/Rules/IsRequired.php) | `?string $message` | Value must be present and not empty |
| [`IsOptional`](core/ValidatedDTO/Rules/IsOptional.php) | *(none)* | Value is optional (skips validation if null) |
| [`IsRequiredWhen`](core/ValidatedDTO/Rules/IsRequiredWhen.php) | `string\|Closure $field, array $values, ?string $message` | Required only when another field matches |
| [`IsNotEmpty`](core/ValidatedDTO/Rules/IsNotEmpty.php) | `?string $message` | Value must not be empty |

### 2.2 Type
| Rule | Params | Description |
|------|--------|-------------|
| [`IsString`](core/ValidatedDTO/Rules/IsString.php) | `?string $message` | Must be a string |
| [`IsInteger`](core/ValidatedDTO/Rules/IsInteger.php) | `?string $message, bool $transform = false` | Must be an integer (auto-casts if transform: true) |
| [`IsFloat`](core/ValidatedDTO/Rules/IsFloat.php) | `?string $message, bool $transform = false` | Must be a float (auto-casts if transform: true) |
| [`IsBoolean`](core/ValidatedDTO/Rules/IsBoolean.php) | `?string $message, bool $transform = false` | Must be boolean (1/0) |
| [`IsNumeric`](core/ValidatedDTO/Rules/IsNumeric.php) | `?string $message` | Must be numeric |
| [`IsList`](core/ValidatedDTO/Rules/IsList.php) | `?string $ruleOrSchema = null, ?string $message` | Must be an indexed array; can nest DTOs or inline rules |
| [`IsMap`](core/ValidatedDTO/Rules/IsMap.php) | `?string $dtoClass = null, ?string $message` | Must be an associative array; can nest DTOs |

### 2.3 Format
| Rule | Params | Description |
|------|--------|-------------|
| [`IsEmail`](core/ValidatedDTO/Rules/IsEmail.php) | `bool $precise = false, ?string $message` | Valid email |
| [`IsDate`](core/ValidatedDTO/Rules/IsDate.php) | `string $format = 'Y-m-d', ?string $message, bool $transform = false` | Valid date; auto-converts to Date object if transform: true |
| [`IsDateString`](core/ValidatedDTO/Rules/IsDateString.php) | `?string $message` | Valid ISO 8601 date string |
| [`IsRegex`](core/ValidatedDTO/Rules/IsRegex.php) | `string $pattern, ?string $message` | Match a regex pattern |
| [`IsNotRegex`](core/ValidatedDTO/Rules/IsNotRegex.php) | `string $pattern, ?string $message` | Must NOT match a regex pattern |
| [`IsUrl`](core/ValidatedDTO/Rules/IsUrl.php) | `?string $message` | Valid URL |
| [`IsUUID`](core/ValidatedDTO/Rules/IsUUID.php) | `?int $version = null, ?string $message` | Valid UUID (use `IsUUID::V4` etc. for version) |
| [`IsPhone`](core/ValidatedDTO/Rules/IsPhone.php) | `?string $region = null, ?string $message` | Valid phone number (e.g. `'vn'`) |
| [`IsJson`](core/ValidatedDTO/Rules/IsJson.php) | `?string $message, bool $transform = false` | Valid JSON string |
| [`IsIP`](core/ValidatedDTO/Rules/IsIP.php) | `?string $message` | Valid IP (v4 or v6) |
| [`IsIPv4`](core/ValidatedDTO/Rules/IsIPv4.php) | `?string $message` | Valid IPv4 |
| [`IsIPv6`](core/ValidatedDTO/Rules/IsIPv6.php) | `?string $message` | Valid IPv6 |
| [`IsMacAddress`](core/ValidatedDTO/Rules/IsMacAddress.php) | `?string $message` | Valid MAC address |
| [`IsLatitude`](core/ValidatedDTO/Rules/IsLatitude.php) | `?string $message` | Latitude (-90 to 90) |
| [`IsLongitude`](core/ValidatedDTO/Rules/IsLongitude.php) | `?string $message` | Longitude (-180 to 180) |
| [`IsLatLong`](core/ValidatedDTO/Rules/IsLatLong.php) | `?string $message` | "lat,lng" string |
| [`IsHexadecimal`](core/ValidatedDTO/Rules/IsHexadecimal.php) | `?string $message` | Hex string |
| [`IsBase64`](core/ValidatedDTO/Rules/IsBase64.php) | `?string $message` | Base64 string |
| [`IsCSSColor`](core/ValidatedDTO/Rules/IsCSSColor.php) | `?string $message` | CSS color (hex/rgb/hsl/named) |

### 2.4 String Content
| Rule | Params | Description |
|------|--------|-------------|
| [`IsAlpha`](core/ValidatedDTO/Rules/IsAlpha.php) | `?string $message` | Only alphabet characters |
| [`IsAlphaDash`](core/ValidatedDTO/Rules/IsAlphaDash.php) | `?string $message` | Alpha, numeric, dash, underscore |
| [`IsAlphaNum`](core/ValidatedDTO/Rules/IsAlphaNum.php) | `?string $message` | Only alphabet and numeric |
| [`IsAlphaSpaces`](core/ValidatedDTO/Rules/IsAlphaSpaces.php) | `?string $message` | Alphabet and spaces |
| [`IsLowercase`](core/ValidatedDTO/Rules/IsLowercase.php) | `bool $transform = false, ?string $message` | Must be lowercase (auto-converts if transform: true) |
| [`IsUppercase`](core/ValidatedDTO/Rules/IsUppercase.php) | `bool $transform = false, ?string $message` | Must be uppercase (auto-converts if transform: true) |
| [`IsStartsWith`](core/ValidatedDTO/Rules/IsStartsWith.php) | `string\|array $prefix, bool $caseSensitive = true, ?string $message` | Must start with given prefix(es) |

### 2.5 Comparison / Size
| Rule | Params | Description |
|------|--------|-------------|
| [`Min`](core/ValidatedDTO/Rules/Min.php) | `float $min, ?string $message` | Min value/length/count |
| [`Max`](core/ValidatedDTO/Rules/Max.php) | `float $max, ?string $message` | Max value/length/count |
| [`Equals`](core/ValidatedDTO/Rules/Equals.php) | `string $field, bool $strict = false, ?string $message` | Must equal another field |
| [`IsGreaterThan`](core/ValidatedDTO/Rules/IsGreaterThan.php) | `string $field, ?string $message` | Must be > another field |
| [`IsGreaterThanOrEqual`](core/ValidatedDTO/Rules/IsGreaterThanOrEqual.php) | `string $field, ?string $message` | Must be >= another field |
| [`IsPositive`](core/ValidatedDTO/Rules/IsPositive.php) | `?string $message` | Must be a positive number |

### 2.6 Enum
| Rule | Params | Description |
|------|--------|-------------|
| [`IsEnum`](core/ValidatedDTO/Rules/IsEnum.php) | `string $enum, array $only = [], array $except = [], bool $transform = false` | Must be a valid backed enum value; auto-converts to enum instance if transform: true |

### 2.7 Database
| Rule | Params | Description |
|------|--------|-------------|
| [`IsUnique`](core/ValidatedDTO/Rules/IsUnique.php) | `string $tableOrModel, ?string $field = null, ?string $where = null, ?string $connection = null, ?string $message` | Value must be unique in DB table |
| [`Exists`](core/ValidatedDTO/Rules/Exists.php) | `string $tableOrModel, string $field = 'id', ?string $where = null, ?string $connection = null, ?string $message` | Value must exist in DB table |

### 2.8 File
| Rule | Params | Description |
|------|--------|-------------|
| [`IsFile`](core/ValidatedDTO/Rules/IsFile.php) | `?string $message` | Must be an uploaded file |
| [`IsImage`](core/ValidatedDTO/Rules/IsImage.php) | `?string $message` | Must be an image (jpeg/png/bmp/gif/svg/webp) |
| [`IsSize`](core/ValidatedDTO/Rules/IsSize.php) | `string\|float $size, ?string $message` | File size / string length / array count limit |
| [`IsMimeType`](core/ValidatedDTO/Rules/IsMimeType.php) | `array $allowMimeTypes, ?string $message` | Must have allowed MIME types |
| [`IsExtension`](core/ValidatedDTO/Rules/IsExtension.php) | `array $allowedExtensions, ?string $message` | Must have allowed file extensions |
| [`IsUploadedFile`](core/ValidatedDTO/Rules/IsUploadedFile.php) | `?int $maxSize, ?int $minSize, array $allowedTypes, ?string $message` | Valid uploaded file with constraints |

### 2.9 Special
| Rule | Params | Description |
|------|--------|-------------|
| [`IsAccepted`](core/ValidatedDTO/Rules/IsAccepted.php) | `?string $message` | Must be yes/on/1/true (for terms) |
| [`IsPassword`](core/ValidatedDTO/Rules/IsPassword.php) | `array $rules` | Password strength (min, max, letters, numbers, mixedCase, symbols, uncompromised) |
| [`IsCustom`](core/ValidatedDTO/Rules/IsCustom.php) | `string $method, string $message` | Custom validation via static method on the DTO |
| [`IsDistinct`](core/ValidatedDTO/Rules/IsDistinct.php) | `bool $strict = false, bool $ignoreCase = false, ?string $message` | Array values must be distinct |
| [`Bail`](core/ValidatedDTO/Rules/Bail.php) | *(none)* | Stop validation on first failure for this property |
| [`Transform`](core/ValidatedDTO/Rules/Transform.php) | `string $method` | Transform value via static method on the DTO |

---

## 3. Examples

### 3.1 Basic Required + Type

```php
class LoginDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsEmail]
    public $email;

    #[IsRequired]
    #[IsString]
    public $password;
}
```

### 3.2 Optional + Enum + Date

```php
class CreateCustomerDTO extends ValidatedDTO
{
    #[IsRequired] #[IsString] public $full_name;
    #[IsRequired] #[IsEmail] public $email;
    #[IsRequired] #[IsString] public $phone;
    #[IsOptional] #[IsEnum(CustomerGender::class)] public $gender;
    #[IsOptional] #[IsDate('Y-m-d')] public $dob;
    #[IsOptional] #[IsInteger] public $country_id;
}
```

### 3.3 Database Uniqueness

```php
class StoreCustomerDTO extends ValidatedDTO
{
    #[IsRequired] #[IsString] public $full_name;
    #[IsRequired] #[IsEmail] #[IsUnique(Customer::class, 'email')] public $email;
    #[IsRequired] #[IsString] public $phone;
    #[IsOptional] #[IsString] #[IsUnique(Customer::class, 'id_number')] public $id_number;
}
```

### 3.4 Existence Check

```php
class AssignCountryDTO extends ValidatedDTO
{
    #[IsRequired]
    #[Exists(Country::class, 'code')]
    public $country_code;
}
```

### 3.5 Regex

```php
class LoginDTO extends ValidatedDTO
{
    #[IsRequired] #[IsEmail] public $email;
    #[IsRequired]
    #[IsRegex("/^(?=.*[a-z])(?=.*[A-Z]).{8,}$/", "Password must be 8+ chars with upper and lower.")]
    public $password;
}
```

### 3.6 Custom Validation

```php
class RegisterCustomerDTO extends ValidatedDTO
{
    #[IsRequired] #[IsEmail]
    #[IsCustom('checkEmailRegisted', 'The email has been registered.')]
    public $email;

    public static function checkEmailRegisted(mixed &$value): bool
    {
        return !App::service(CustomerServiceInterface::class)->checkExistEmail($value);
    }
}
```

### 3.7 Password Strength

```php
class CompleteRegisterDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsPassword([
        'min' => ['min' => 8, 'message' => 'Min 8 characters.'],
        'max' => ['max' => 16, 'message' => 'Max 16 characters.'],
        'letters' => ['message' => 'Must contain a letter.'],
        'numbers' => ['message' => 'Must contain a number.'],
        'mixedCase' => ['message' => 'Must have upper and lower case.'],
        'symbols' => ['message' => 'Must contain a symbol.'],
    ])]
    public $password;

    #[IsRequired] #[Equals('password')] public $confirm_password;
}
```

### 3.8 Unique with Custom Where (Update)

```php
class UpdateCustomerDTO extends ValidatedDTO
{
    #[IsOptional] #[IsString]
    #[IsUnique(tableOrModel: Customer::class, field: 'full_name', where: 'whereUniqueCustomer')]
    public $full_name;

    #[IsRequired] #[IsEmail]
    #[IsUnique(tableOrModel: Customer::class, field: 'email', where: 'whereEmail')]
    public $email;

    public static function whereUniqueCustomer($query, $value, $values): void
    {
        if (isset($values['id'])) $query->where('id', $values['id'], '!=');
    }

    public static function whereEmail($query, $value, $values): void
    {
        if (isset($values['email'])) $query->where('email', $values['email'], '!=');
    }
}
```

### 3.9 Nested DTO (IsList + DTO class)

```php
class CountryDTO extends ValidatedDTO
{
    #[IsOptional]
    #[IsList(SyncCountry::class)]
    public $countries;
}

class SyncCountry extends ValidatedDTO
{
    #[IsRequired] #[IsString] public $name;
    #[IsRequired] #[IsString] public $slug;
    #[IsRequired] #[IsInteger] public $code;
    #[IsRequired] #[IsEnum(Language::class)] public $lang_id;
}
```

### 3.10 Nested DTO (IsMap + DTO class)

```php
class OrderDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsMap(AddressDTO::class)]
    public $shipping_address;
}

class AddressDTO extends ValidatedDTO
{
    #[IsRequired] #[IsString] public $street;
    #[IsRequired] #[IsString] public $city;
    #[IsRequired] #[IsString] public $country;
}
```

### 3.11 IsList + Inline Rule

```php
class TagDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsList(IsString::class)]  // each item must be a string
    public $tags;
}
```

### 3.12 Transform

```php
class ExampleDTO extends ValidatedDTO
{
    #[IsRequired]
    #[Transform('slugify')]
    public $title;

    public static function slugify(mixed $value): string
    {
        return strtolower(str_replace(' ', '-', $value));
    }
}
```

### 3.13 Bail (Stop on First Error)

```php
class RegisterDTO extends ValidatedDTO
{
    #[IsRequired] #[Bail] #[IsEmail] #[IsUnique(Customer::class, 'email')]
    public $email;
}
```

### 3.14 Transform (Integer/Float/Date/Enum)

```php
class ProductDTO extends ValidatedDTO
{
    #[IsRequired] #[IsInteger(transform: true)] public $quantity;  // auto-casts to int
    #[IsRequired] #[IsFloat(transform: true)]   public $price;     // auto-casts to float
}

class EventDTO extends ValidatedDTO
{
    #[IsRequired] #[IsDate(format: 'Y-m-d', transform: true)] public $event_date;  // auto-converts to Date object
}

class CustomerDTO extends ValidatedDTO
{
    #[IsRequired] #[IsEnum(CustomerType::class, transform: true)] public $type;  // auto-converts to enum instance
}
```

### 3.15 IsRequiredWhen

```php
class ContactDTO extends ValidatedDTO
{
    #[IsRequired] #[IsString] public $contact_method;
    #[IsRequiredWhen('contact_method', ['email'])] #[IsEmail] public $email;
    #[IsRequiredWhen('contact_method', ['phone'])] #[IsString] public $phone;
}
```

### 3.16 UUID + Phone

```php
class ResendRegistrationDTO extends ValidatedDTO
{
    #[IsRequired] #[IsString] #[IsUUID(IsUUID::V4)] public $token;
}

class UpdateProfile extends ValidatedDTO
{
    #[IsRequired] #[IsPhone('vn')] public $phone;
    #[IsRequired] #[IsPhone('vn')] public $zalo_phone;
}
```

### 3.17 Min/Max

```php
class ProductDTO extends ValidatedDTO
{
    #[IsRequired] #[IsString] #[Min(10)] #[Max(255)] public $name;
    #[IsRequired] #[Min(0)] #[Max(1000000)] public $price;
}
```

### 3.18 IsGreaterThan / IsGreaterThanOrEqual

```php
class DateRangeDTO extends ValidatedDTO
{
    #[IsRequired] #[IsInteger] public $start_date;
    #[IsRequired] #[IsInteger] #[IsGreaterThan('start_date')] public $end_date;
    #[IsRequired] #[IsInteger] #[IsGreaterThanOrEqual('start_date')] public $min_guests;
}
```

### 3.19 IsDistinct + IsAccepted

```php
class SelectionDTO extends ValidatedDTO
{
    #[IsRequired] #[IsList] #[IsDistinct(strict: true)] public $selected_ids;
}

class TermsDTO extends ValidatedDTO
{
    #[IsRequired] #[IsAccepted] public $agree_terms;
}
```

---

## 4. Usage in Controllers

```php
class CustomerController extends Controller
{
    public function store(Request $request): Response
    {
        try {
            $dto = new StoreCustomerDTO($request->all(), validate: true);
            // or: $dto = StoreCustomerDTO::fromArray($request->all(), validate: true);

            $data = $dto->toArray();
            $subset = $dto->only(['name', 'email']);
            $without = $dto->except(['password']);

            return Response::json(['data' => $data]);
        } catch (ValidationException $e) {
            return Response::json([
                'message' => $e->getMessage(),
                'errors' => $e->getErrors()
            ], $e->getStatusCode());
        }
    }
}
```

---

## 5. BaseDTO (Without Validation)

For internal data transfer without validation, use [`BaseDTO`](core/BaseDTO/BaseDTO.php):

```php
class InvoiceDTO extends BaseDTO
{
    public int $booking_id;
    public InvoiceStatusEnum $status;
    public float $total_amount;
    public Date $accounting_date;
}
```

Methods: [`toArray()`](core/BaseDTO/BaseDTO.php:55), [`toJson()`](core/BaseDTO/BaseDTO.php:65), [`only()`](core/BaseDTO/BaseDTO.php:25), [`except()`](core/BaseDTO/BaseDTO.php:30), [`fromArray()`](core/BaseDTO/BaseDTO.php:35), [`fromJson()`](core/BaseDTO/BaseDTO.php:40), [`all()`](core/BaseDTO/BaseDTO.php:50), [`toModel()`](core/BaseDTO/BaseDTO.php:70). Implements `IteratorAggregate`, `Arrayable`, `JsonSerializable`.

---

## 6. Quick Reference

| Category | Rules |
|----------|-------|
| **Presence** | [`IsRequired`](core/ValidatedDTO/Rules/IsRequired.php), [`IsOptional`](core/ValidatedDTO/Rules/IsOptional.php), [`IsRequiredWhen`](core/ValidatedDTO/Rules/IsRequiredWhen.php), [`IsNotEmpty`](core/ValidatedDTO/Rules/IsNotEmpty.php) |
| **Type** | [`IsString`](core/ValidatedDTO/Rules/IsString.php), [`IsInteger`](core/ValidatedDTO/Rules/IsInteger.php), [`IsFloat`](core/ValidatedDTO/Rules/IsFloat.php), [`IsBoolean`](core/ValidatedDTO/Rules/IsBoolean.php), [`IsNumeric`](core/ValidatedDTO/Rules/IsNumeric.php), [`IsList`](core/ValidatedDTO/Rules/IsList.php), [`IsMap`](core/ValidatedDTO/Rules/IsMap.php) |
| **Format** | [`IsEmail`](core/ValidatedDTO/Rules/IsEmail.php), [`IsDate`](core/ValidatedDTO/Rules/IsDate.php), [`IsDateString`](core/ValidatedDTO/Rules/IsDateString.php), [`IsRegex`](core/ValidatedDTO/Rules/IsRegex.php), [`IsNotRegex`](core/ValidatedDTO/Rules/IsNotRegex.php), [`IsUrl`](core/ValidatedDTO/Rules/IsUrl.php), [`IsUUID`](core/ValidatedDTO/Rules/IsUUID.php), [`IsPhone`](core/ValidatedDTO/Rules/IsPhone.php), [`IsJson`](core/ValidatedDTO/Rules/IsJson.php), [`IsIP`](core/ValidatedDTO/Rules/IsIP.php), [`IsIPv4`](core/ValidatedDTO/Rules/IsIPv4.php), [`IsIPv6`](core/ValidatedDTO/Rules/IsIPv6.php), [`IsMacAddress`](core/ValidatedDTO/Rules/IsMacAddress.php), [`IsLatitude`](core/ValidatedDTO/Rules/IsLatitude.php), [`IsLongitude`](core/ValidatedDTO/Rules/IsLongitude.php), [`IsLatLong`](core/ValidatedDTO/Rules/IsLatLong.php), [`IsHexadecimal`](core/ValidatedDTO/Rules/IsHexadecimal.php), [`IsBase64`](core/ValidatedDTO/Rules/IsBase64.php), [`IsCSSColor`](core/ValidatedDTO/Rules/IsCSSColor.php) |
| **String** | [`IsAlpha`](core/ValidatedDTO/Rules/IsAlpha.php), [`IsAlphaDash`](core/ValidatedDTO/Rules/IsAlphaDash.php), [`IsAlphaNum`](core/ValidatedDTO/Rules/IsAlphaNum.php), [`IsAlphaSpaces`](core/ValidatedDTO/Rules/IsAlphaSpaces.php), [`IsLowercase`](core/ValidatedDTO/Rules/IsLowercase.php), [`IsUppercase`](core/ValidatedDTO/Rules/IsUppercase.php), [`IsStartsWith`](core/ValidatedDTO/Rules/IsStartsWith.php) |
| **Comparison** | [`Min`](core/ValidatedDTO/Rules/Min.php), [`Max`](core/ValidatedDTO/Rules/Max.php), [`Equals`](core/ValidatedDTO/Rules/Equals.php), [`IsGreaterThan`](core/ValidatedDTO/Rules/IsGreaterThan.php), [`IsGreaterThanOrEqual`](core/ValidatedDTO/Rules/IsGreaterThanOrEqual.php), [`IsPositive`](core/ValidatedDTO/Rules/IsPositive.php) |
| **Enum** | [`IsEnum`](core/ValidatedDTO/Rules/IsEnum.php) |
| **Database** | [`IsUnique`](core/ValidatedDTO/Rules/IsUnique.php), [`Exists`](core/ValidatedDTO/Rules/Exists.php) |
| **File** | [`IsFile`](core/ValidatedDTO/Rules/IsFile.php), [`IsImage`](core/ValidatedDTO/Rules/IsImage.php), [`IsSize`](core/ValidatedDTO/Rules/IsSize.php), [`IsMimeType`](core/ValidatedDTO/Rules/IsMimeType.php), [`IsExtension`](core/ValidatedDTO/Rules/IsExtension.php), [`IsUploadedFile`](core/ValidatedDTO/Rules/IsUploadedFile.php) |
| **Special** | [`IsAccepted`](core/ValidatedDTO/Rules/IsAccepted.php), [`IsPassword`](core/ValidatedDTO/Rules/IsPassword.php), [`IsCustom`](core/ValidatedDTO/Rules/IsCustom.php), [`IsDistinct`](core/ValidatedDTO/Rules/IsDistinct.php), [`Bail`](core/ValidatedDTO/Rules/Bail.php), [`Transform`](core/ValidatedDTO/Rules/Transform.php) |
