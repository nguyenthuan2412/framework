# Tạo DTO (Data Transfer Object)

## Tổng quan

DTO trong Ryra có hai mục đích:
1. **Truyền dữ liệu**: mang dữ liệu có cấu trúc giữa các tầng (Controller → Service, ...)
2. **Validate**: tự kiểm tra dữ liệu request bằng PHP 8 attributes

Có hai class cha:
- [`BaseDTO`](../BaseDTO/BaseDTO.php): DTO đơn giản, không validate, dùng để truyền dữ liệu nội bộ
- [`ValidatedDTO`](../ValidatedDTO/ValidatedDTO.php): DTO có rule validate khai báo bằng attribute, dùng cho dữ liệu request

Tạo nhanh DTO bằng lệnh:

```bash
php ryra gen:dto StoreCustomer --module=customer
```

---

## 1. Cấu trúc cơ bản

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

### Ghi nhớ:
- Kế thừa [`ValidatedDTO`](../ValidatedDTO/ValidatedDTO.php)
- Property để **public** và không khai báo kiểu, việc kiểm tra kiểu do các rule đảm nhận
- Mỗi property có thể gắn **nhiều rule** cùng lúc
- Khi khai báo DTO làm tham số của method trong controller, framework tự validate. Khi tự tạo, validate chạy nếu truyền `$validate = true` hoặc gọi [`fromArray($data, true)`](../ValidatedDTO/ValidatedDTO.php#L187)
- Lấy giá trị bằng `$dto->property`

---

## 2. Danh sách rule

### 2.1 Bắt buộc / tuỳ chọn
| Rule | Tham số | Mô tả |
|------|--------|-------------|
| [`IsRequired`](../ValidatedDTO/Rules/IsRequired.php) | `?string $message` | Bắt buộc có giá trị và không rỗng |
| [`IsOptional`](../ValidatedDTO/Rules/IsOptional.php) | *(none)* | Không bắt buộc (bỏ qua validate nếu null) |
| [`IsRequiredWhen`](../ValidatedDTO/Rules/IsRequiredWhen.php) | `string\|Closure $field, array $values, ?string $message` | Chỉ bắt buộc khi field khác có giá trị khớp |
| [`IsNotEmpty`](../ValidatedDTO/Rules/IsNotEmpty.php) | `?string $message` | Không được rỗng |

### 2.2 Kiểu dữ liệu
| Rule | Tham số | Mô tả |
|------|--------|-------------|
| [`IsString`](../ValidatedDTO/Rules/IsString.php) | `?string $message` | Phải là chuỗi |
| [`IsInteger`](../ValidatedDTO/Rules/IsInteger.php) | `?string $message, bool $transform = false` | Phải là số nguyên (tự ép kiểu nếu `transform: true`) |
| [`IsFloat`](../ValidatedDTO/Rules/IsFloat.php) | `?string $message, bool $transform = false` | Phải là số thực (tự ép kiểu nếu `transform: true`) |
| [`IsBoolean`](../ValidatedDTO/Rules/IsBoolean.php) | `?string $message, bool $transform = false` | Phải là boolean (1/0) |
| [`IsNumeric`](../ValidatedDTO/Rules/IsNumeric.php) | `?string $message` | Phải là số |
| [`IsList`](../ValidatedDTO/Rules/IsList.php) | `?string $ruleOrSchema = null, ?string $message` | Phải là mảng tuần tự; có thể lồng DTO hoặc rule cho từng phần tử |
| [`IsMap`](../ValidatedDTO/Rules/IsMap.php) | `?string $dtoClass = null, ?string $message` | Phải là mảng key-value; có thể lồng DTO |

### 2.3 Định dạng
| Rule | Tham số | Mô tả |
|------|--------|-------------|
| [`IsEmail`](../ValidatedDTO/Rules/IsEmail.php) | `bool $precise = false, ?string $message` | Email hợp lệ |
| [`IsDate`](../ValidatedDTO/Rules/IsDate.php) | `string $format = 'Y-m-d', ?string $message, bool $transform = false` | Ngày hợp lệ; tự chuyển thành đối tượng Date nếu `transform: true` |
| [`IsDateString`](../ValidatedDTO/Rules/IsDateString.php) | `?string $message` | Chuỗi ngày theo chuẩn ISO 8601 |
| [`IsRegex`](../ValidatedDTO/Rules/IsRegex.php) | `string $pattern, ?string $message` | Khớp với biểu thức regex |
| [`IsNotRegex`](../ValidatedDTO/Rules/IsNotRegex.php) | `string $pattern, ?string $message` | KHÔNG được khớp với biểu thức regex |
| [`IsUrl`](../ValidatedDTO/Rules/IsUrl.php) | `?string $message` | URL hợp lệ |
| [`IsUUID`](../ValidatedDTO/Rules/IsUUID.php) | `?int $version = null, ?string $message` | UUID hợp lệ (dùng `IsUUID::V4`, ... để chỉ định phiên bản) |
| [`IsPhone`](../ValidatedDTO/Rules/IsPhone.php) | `?string $region = null, ?string $message` | Số điện thoại hợp lệ theo vùng (ví dụ `'vn'`) |
| [`IsJson`](../ValidatedDTO/Rules/IsJson.php) | `?string $message, bool $transform = false` | Chuỗi JSON hợp lệ |
| [`IsIP`](../ValidatedDTO/Rules/IsIP.php) | `?string $message` | Địa chỉ IP hợp lệ (v4 hoặc v6) |
| [`IsIPv4`](../ValidatedDTO/Rules/IsIPv4.php) | `?string $message` | Địa chỉ IPv4 hợp lệ |
| [`IsIPv6`](../ValidatedDTO/Rules/IsIPv6.php) | `?string $message` | Địa chỉ IPv6 hợp lệ |
| [`IsMacAddress`](../ValidatedDTO/Rules/IsMacAddress.php) | `?string $message` | Địa chỉ MAC hợp lệ |
| [`IsLatitude`](../ValidatedDTO/Rules/IsLatitude.php) | `?string $message` | Vĩ độ (-90 đến 90) |
| [`IsLongitude`](../ValidatedDTO/Rules/IsLongitude.php) | `?string $message` | Kinh độ (-180 đến 180) |
| [`IsLatLong`](../ValidatedDTO/Rules/IsLatLong.php) | `?string $message` | Chuỗi dạng "vĩ độ,kinh độ" |
| [`IsHexadecimal`](../ValidatedDTO/Rules/IsHexadecimal.php) | `?string $message` | Chuỗi hexa |
| [`IsBase64`](../ValidatedDTO/Rules/IsBase64.php) | `?string $message` | Chuỗi Base64 |
| [`IsCSSColor`](../ValidatedDTO/Rules/IsCSSColor.php) | `?string $message` | Màu CSS (hex/rgb/hsl/tên màu) |

### 2.4 Nội dung chuỗi
| Rule | Tham số | Mô tả |
|------|--------|-------------|
| [`IsAlpha`](../ValidatedDTO/Rules/IsAlpha.php) | `?string $message` | Chỉ chứa chữ cái |
| [`IsAlphaDash`](../ValidatedDTO/Rules/IsAlphaDash.php) | `?string $message` | Chữ cái, số, gạch ngang, gạch dưới |
| [`IsAlphaNum`](../ValidatedDTO/Rules/IsAlphaNum.php) | `?string $message` | Chỉ chứa chữ cái và số |
| [`IsAlphaSpaces`](../ValidatedDTO/Rules/IsAlphaSpaces.php) | `?string $message` | Chữ cái và khoảng trắng |
| [`IsLowercase`](../ValidatedDTO/Rules/IsLowercase.php) | `bool $transform = false, ?string $message` | Phải là chữ thường (tự chuyển nếu `transform: true`) |
| [`IsUppercase`](../ValidatedDTO/Rules/IsUppercase.php) | `bool $transform = false, ?string $message` | Phải là chữ hoa (tự chuyển nếu `transform: true`) |
| [`IsStartsWith`](../ValidatedDTO/Rules/IsStartsWith.php) | `string\|array $prefix, bool $caseSensitive = true, ?string $message` | Phải bắt đầu bằng (một trong) các tiền tố cho trước |

### 2.5 So sánh / kích thước
| Rule | Tham số | Mô tả |
|------|--------|-------------|
| [`Min`](../ValidatedDTO/Rules/Min.php) | `float $min, ?string $message` | Giá trị / độ dài / số phần tử tối thiểu |
| [`Max`](../ValidatedDTO/Rules/Max.php) | `float $max, ?string $message` | Giá trị / độ dài / số phần tử tối đa |
| [`Equals`](../ValidatedDTO/Rules/Equals.php) | `string $field, bool $strict = false, ?string $message` | Phải bằng giá trị của field khác |
| [`IsGreaterThan`](../ValidatedDTO/Rules/IsGreaterThan.php) | `string $field, ?string $message` | Phải lớn hơn field khác |
| [`IsGreaterThanOrEqual`](../ValidatedDTO/Rules/IsGreaterThanOrEqual.php) | `string $field, ?string $message` | Phải lớn hơn hoặc bằng field khác |
| [`IsPositive`](../ValidatedDTO/Rules/IsPositive.php) | `?string $message` | Phải là số dương |

### 2.6 Enum
| Rule | Tham số | Mô tả |
|------|--------|-------------|
| [`IsEnum`](../ValidatedDTO/Rules/IsEnum.php) | `string $enum, array $only = [], array $except = [], bool $transform = false` | Phải là giá trị hợp lệ của backed enum; tự chuyển thành enum nếu `transform: true` |

### 2.7 Database
| Rule | Tham số | Mô tả |
|------|--------|-------------|
| [`IsUnique`](../ValidatedDTO/Rules/IsUnique.php) | `string $tableOrModel, ?string $field = null, ?string $where = null, ?string $connection = null, ?string $message` | Giá trị chưa tồn tại trong bảng (duy nhất) |
| [`Exists`](../ValidatedDTO/Rules/Exists.php) | `string $tableOrModel, string $field = 'id', ?string $where = null, ?string $connection = null, ?string $message` | Giá trị phải tồn tại trong bảng |

### 2.8 File
| Rule | Tham số | Mô tả |
|------|--------|-------------|
| [`IsFile`](../ValidatedDTO/Rules/IsFile.php) | `?string $message` | Phải là file upload |
| [`IsImage`](../ValidatedDTO/Rules/IsImage.php) | `?string $message` | Phải là ảnh (jpeg/png/bmp/gif/svg/webp) |
| [`IsSize`](../ValidatedDTO/Rules/IsSize.php) | `string\|float $size, ?string $message` | Giới hạn dung lượng file / độ dài chuỗi / số phần tử mảng |
| [`IsMimeType`](../ValidatedDTO/Rules/IsMimeType.php) | `array $allowMimeTypes, ?string $message` | MIME type phải nằm trong danh sách cho phép |
| [`IsExtension`](../ValidatedDTO/Rules/IsExtension.php) | `array $allowedExtensions, ?string $message` | Đuôi file phải nằm trong danh sách cho phép |
| [`IsUploadedFile`](../ValidatedDTO/Rules/IsUploadedFile.php) | `?int $maxSize, ?int $minSize, array $allowedTypes, ?string $message` | File upload hợp lệ theo các ràng buộc (kích thước, loại) |

### 2.9 Đặc biệt
| Rule | Tham số | Mô tả |
|------|--------|-------------|
| [`IsAccepted`](../ValidatedDTO/Rules/IsAccepted.php) | `?string $message` | Phải là yes/on/1/true (dùng cho đồng ý điều khoản) |
| [`IsPassword`](../ValidatedDTO/Rules/IsPassword.php) | `array $rules` | Độ mạnh mật khẩu (min, max, letters, numbers, mixedCase, symbols, uncompromised) |
| [`IsCustom`](../ValidatedDTO/Rules/IsCustom.php) | `string $method, string $message` | Validate tuỳ biến bằng static method trong DTO |
| [`IsDistinct`](../ValidatedDTO/Rules/IsDistinct.php) | `bool $strict = false, bool $ignoreCase = false, ?string $message` | Các phần tử trong mảng không được trùng nhau |
| [`Bail`](../ValidatedDTO/Rules/Bail.php) | *(none)* | Dừng validate field này ngay khi gặp lỗi đầu tiên |
| [`Transform`](../ValidatedDTO/Rules/Transform.php) | `string $method` | Biến đổi giá trị bằng static method trong DTO |

---

## 3. Ví dụ

### 3.1 Bắt buộc + kiểu dữ liệu

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

### 3.2 Tuỳ chọn + Enum + Ngày

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

### 3.3 Kiểm tra trùng trong database

```php
class StoreCustomerDTO extends ValidatedDTO
{
    #[IsRequired] #[IsString] public $full_name;
    #[IsRequired] #[IsEmail] #[IsUnique(Customer::class, 'email')] public $email;
    #[IsRequired] #[IsString] public $phone;
    #[IsOptional] #[IsString] #[IsUnique(Customer::class, 'id_number')] public $id_number;
}
```

### 3.4 Kiểm tra tồn tại

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

### 3.6 Validate tuỳ biến

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

### 3.7 Độ mạnh mật khẩu

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

### 3.8 Unique với điều kiện where tuỳ biến (khi cập nhật)

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

### 3.9 DTO lồng nhau (IsList + class DTO)

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

### 3.10 DTO lồng nhau (IsMap + class DTO)

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

### 3.11 IsList + rule cho từng phần tử

```php
class TagDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsList(IsString::class)]  // mỗi phần tử phải là chuỗi
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

### 3.13 Bail (dừng ở lỗi đầu tiên)

```php
class RegisterDTO extends ValidatedDTO
{
    #[IsRequired] #[Bail] #[IsEmail] #[IsUnique(Customer::class, 'email')]
    public $email;
}
```

### 3.14 Tự chuyển kiểu (Integer/Float/Date/Enum)

```php
class ProductDTO extends ValidatedDTO
{
    #[IsRequired] #[IsInteger(transform: true)] public $quantity;  // tự ép về int
    #[IsRequired] #[IsFloat(transform: true)]   public $price;     // tự ép về float
}

class EventDTO extends ValidatedDTO
{
    #[IsRequired] #[IsDate(format: 'Y-m-d', transform: true)] public $event_date;  // tự chuyển thành đối tượng Date
}

class CustomerDTO extends ValidatedDTO
{
    #[IsRequired] #[IsEnum(CustomerType::class, transform: true)] public $type;  // tự chuyển thành enum
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

## 4. Dùng trong Controller

Cách thường dùng nhất là khai báo DTO làm tham số của method. Framework tự validate và tự trả lỗi nếu dữ liệu sai:

```php
#[Post(uri: '/')]
public function store(StoreCustomerDTO $dto): JsonResponse
{
    return Response::json(['data' => $dto->toArray()], 201);
}
```

Nếu muốn tự xử lý lỗi validate, tạo DTO thủ công:

```php
class CustomerController extends Controller
{
    public function store(Request $request): Response
    {
        try {
            $dto = new StoreCustomerDTO($request->all(), validate: true);
            // hoặc: $dto = StoreCustomerDTO::fromArray($request->all(), validate: true);

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

## 5. BaseDTO (không validate)

Để truyền dữ liệu nội bộ mà không cần validate, dùng [`BaseDTO`](../BaseDTO/BaseDTO.php):

```php
class InvoiceDTO extends BaseDTO
{
    public int $booking_id;
    public InvoiceStatusEnum $status;
    public float $total_amount;
    public Date $accounting_date;
}
```

Các method: [`toArray()`](../BaseDTO/BaseDTO.php#L55), [`toJson()`](../BaseDTO/BaseDTO.php#L65), [`only()`](../BaseDTO/BaseDTO.php#L25), [`except()`](../BaseDTO/BaseDTO.php#L30), [`fromArray()`](../BaseDTO/BaseDTO.php#L35), [`fromJson()`](../BaseDTO/BaseDTO.php#L40), [`all()`](../BaseDTO/BaseDTO.php#L50), [`toModel()`](../BaseDTO/BaseDTO.php#L70). Implement `IteratorAggregate`, `Arrayable`, `JsonSerializable`.

---

## 6. Tra cứu nhanh

| Nhóm | Rule |
|----------|-------|
| **Bắt buộc / tuỳ chọn** | [`IsRequired`](../ValidatedDTO/Rules/IsRequired.php), [`IsOptional`](../ValidatedDTO/Rules/IsOptional.php), [`IsRequiredWhen`](../ValidatedDTO/Rules/IsRequiredWhen.php), [`IsNotEmpty`](../ValidatedDTO/Rules/IsNotEmpty.php) |
| **Kiểu dữ liệu** | [`IsString`](../ValidatedDTO/Rules/IsString.php), [`IsInteger`](../ValidatedDTO/Rules/IsInteger.php), [`IsFloat`](../ValidatedDTO/Rules/IsFloat.php), [`IsBoolean`](../ValidatedDTO/Rules/IsBoolean.php), [`IsNumeric`](../ValidatedDTO/Rules/IsNumeric.php), [`IsList`](../ValidatedDTO/Rules/IsList.php), [`IsMap`](../ValidatedDTO/Rules/IsMap.php) |
| **Định dạng** | [`IsEmail`](../ValidatedDTO/Rules/IsEmail.php), [`IsDate`](../ValidatedDTO/Rules/IsDate.php), [`IsDateString`](../ValidatedDTO/Rules/IsDateString.php), [`IsRegex`](../ValidatedDTO/Rules/IsRegex.php), [`IsNotRegex`](../ValidatedDTO/Rules/IsNotRegex.php), [`IsUrl`](../ValidatedDTO/Rules/IsUrl.php), [`IsUUID`](../ValidatedDTO/Rules/IsUUID.php), [`IsPhone`](../ValidatedDTO/Rules/IsPhone.php), [`IsJson`](../ValidatedDTO/Rules/IsJson.php), [`IsIP`](../ValidatedDTO/Rules/IsIP.php), [`IsIPv4`](../ValidatedDTO/Rules/IsIPv4.php), [`IsIPv6`](../ValidatedDTO/Rules/IsIPv6.php), [`IsMacAddress`](../ValidatedDTO/Rules/IsMacAddress.php), [`IsLatitude`](../ValidatedDTO/Rules/IsLatitude.php), [`IsLongitude`](../ValidatedDTO/Rules/IsLongitude.php), [`IsLatLong`](../ValidatedDTO/Rules/IsLatLong.php), [`IsHexadecimal`](../ValidatedDTO/Rules/IsHexadecimal.php), [`IsBase64`](../ValidatedDTO/Rules/IsBase64.php), [`IsCSSColor`](../ValidatedDTO/Rules/IsCSSColor.php) |
| **Chuỗi** | [`IsAlpha`](../ValidatedDTO/Rules/IsAlpha.php), [`IsAlphaDash`](../ValidatedDTO/Rules/IsAlphaDash.php), [`IsAlphaNum`](../ValidatedDTO/Rules/IsAlphaNum.php), [`IsAlphaSpaces`](../ValidatedDTO/Rules/IsAlphaSpaces.php), [`IsLowercase`](../ValidatedDTO/Rules/IsLowercase.php), [`IsUppercase`](../ValidatedDTO/Rules/IsUppercase.php), [`IsStartsWith`](../ValidatedDTO/Rules/IsStartsWith.php) |
| **So sánh** | [`Min`](../ValidatedDTO/Rules/Min.php), [`Max`](../ValidatedDTO/Rules/Max.php), [`Equals`](../ValidatedDTO/Rules/Equals.php), [`IsGreaterThan`](../ValidatedDTO/Rules/IsGreaterThan.php), [`IsGreaterThanOrEqual`](../ValidatedDTO/Rules/IsGreaterThanOrEqual.php), [`IsPositive`](../ValidatedDTO/Rules/IsPositive.php) |
| **Enum** | [`IsEnum`](../ValidatedDTO/Rules/IsEnum.php) |
| **Database** | [`IsUnique`](../ValidatedDTO/Rules/IsUnique.php), [`Exists`](../ValidatedDTO/Rules/Exists.php) |
| **File** | [`IsFile`](../ValidatedDTO/Rules/IsFile.php), [`IsImage`](../ValidatedDTO/Rules/IsImage.php), [`IsSize`](../ValidatedDTO/Rules/IsSize.php), [`IsMimeType`](../ValidatedDTO/Rules/IsMimeType.php), [`IsExtension`](../ValidatedDTO/Rules/IsExtension.php), [`IsUploadedFile`](../ValidatedDTO/Rules/IsUploadedFile.php) |
| **Đặc biệt** | [`IsAccepted`](../ValidatedDTO/Rules/IsAccepted.php), [`IsPassword`](../ValidatedDTO/Rules/IsPassword.php), [`IsCustom`](../ValidatedDTO/Rules/IsCustom.php), [`IsDistinct`](../ValidatedDTO/Rules/IsDistinct.php), [`Bail`](../ValidatedDTO/Rules/Bail.php), [`Transform`](../ValidatedDTO/Rules/Transform.php) |
