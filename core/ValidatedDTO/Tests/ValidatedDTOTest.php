<?php

namespace Ryra\Core\ValidatedDTO\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\ValidatedDTO\ValidatedDTO;
use Ryra\Core\ValidatedDTO\ValidationException;
use Ryra\Core\ValidatedDTO\Rules\IsRequired;
use Ryra\Core\ValidatedDTO\Rules\IsOptional;
use Ryra\Core\ValidatedDTO\Rules\IsString;
use Ryra\Core\ValidatedDTO\Rules\IsEmail;
use Ryra\Core\ValidatedDTO\Rules\IsInteger;
use Ryra\Core\ValidatedDTO\Rules\IsBoolean;
use Ryra\Core\ValidatedDTO\Rules\IsList;
use Ryra\Core\ValidatedDTO\Rules\IsMap;
use Ryra\Core\ValidatedDTO\Rules\Min;
use Ryra\Core\ValidatedDTO\Rules\Max;
use Ryra\Core\ValidatedDTO\Rules\IsRequiredWhen;
use Ryra\Core\ValidatedDTO\Rules\Bail;
use Ryra\Core\ValidatedDTO\Rules\Equals;
use Ryra\Core\ValidatedDTO\Rules\IsEnum;
use Ryra\Core\ValidatedDTO\Rules\IsIn;
use Ryra\Core\ValidatedDTO\Rules\IsNotIn;
use Ryra\Core\ValidatedDTO\Rules\IsCustom;
use Ryra\Core\ValidatedDTO\Rules\Transform;
use Ryra\Core\ValidatedDTO\Rules\IsDistinct;
use Ryra\Core\ValidatedDTO\Rules\IsFloat;
use Ryra\Core\ValidatedDTO\Rules\IsNumeric;
use Ryra\Core\ValidatedDTO\Rules\IsPositive;
use Ryra\Core\ValidatedDTO\Rules\IsGreaterThan;
use Ryra\Core\ValidatedDTO\Rules\IsGreaterThanOrEqual;
use Ryra\Core\ValidatedDTO\Rules\IsNotEmpty;
use Ryra\Core\ValidatedDTO\Rules\IsRegex;
use Ryra\Core\ValidatedDTO\Rules\IsNotRegex;
use Ryra\Core\ValidatedDTO\Rules\IsUrl;
use Ryra\Core\ValidatedDTO\Rules\IsUUID;
use Ryra\Core\ValidatedDTO\Rules\IsAlpha;
use Ryra\Core\ValidatedDTO\Rules\IsAlphaDash;
use Ryra\Core\ValidatedDTO\Rules\IsAlphaNum;
use Ryra\Core\ValidatedDTO\Rules\IsAlphaSpaces;
use Ryra\Core\ValidatedDTO\Rules\IsUppercase;
use Ryra\Core\ValidatedDTO\Rules\IsLowercase;
use Ryra\Core\ValidatedDTO\Rules\IsStartsWith;
use Ryra\Core\ValidatedDTO\Rules\IsDate;
use Ryra\Core\ValidatedDTO\Rules\IsDateString;
use Ryra\Core\ValidatedDTO\Rules\IsIP;
use Ryra\Core\ValidatedDTO\Rules\IsIPv4;
use Ryra\Core\ValidatedDTO\Rules\IsIPv6;
use Ryra\Core\ValidatedDTO\Rules\IsMacAddress;
use Ryra\Core\ValidatedDTO\Rules\IsLatLong;
use Ryra\Core\ValidatedDTO\Rules\IsLatitude;
use Ryra\Core\ValidatedDTO\Rules\IsLongitude;
use Ryra\Core\ValidatedDTO\Rules\IsCSSColor;
use Ryra\Core\ValidatedDTO\Rules\IsHexadecimal;
use Ryra\Core\ValidatedDTO\Rules\IsBase64;
use Ryra\Core\ValidatedDTO\Rules\IsJson;
use Ryra\Core\ValidatedDTO\Rules\IsAccepted;
use Ryra\Core\ValidatedDTO\Rules\IsSize;
use Ryra\Core\ValidatedDTO\Property;

// ──────────────────────────────────────────────────────────────────────────────
// Fixture DTOs
// ──────────────────────────────────────────────────────────────────────────────

class FixtureUserDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    public mixed $name = null;

    #[IsRequired]
    #[IsEmail]
    public mixed $email = null;
}

class FixtureOptionalAgeDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    public mixed $name = null;

    #[IsOptional]
    #[IsInteger]
    public mixed $age = null;
}

class FixtureAddressDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    public mixed $street = null;

    #[IsRequired]
    #[IsString]
    public mixed $city = null;
}

class FixtureUserWithAddressDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    public mixed $name = null;

    #[IsMap(FixtureAddressDTO::class)]
    public mixed $address = null;
}

class FixtureTagListDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsList]
    public mixed $tags = null;
}

class FixtureUsernameDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    #[Min(3)]
    #[Max(10)]
    public mixed $username = null;
}

class FixtureConditionalDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    public mixed $type = null;

    #[IsRequiredWhen('type', ['admin'])]
    public mixed $access_code = null;
}

class FixtureBooleanDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsBoolean]
    public mixed $active = null;
}

class FixtureStringListDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsList(IsString::class)]
    public mixed $items = null;
}

class FixtureItemDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    public mixed $label = null;
}

class FixtureDtoListDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsList(FixtureItemDTO::class)]
    public mixed $rows = null;
}

// ── Enum fixture ──────────────────────────────────────────────────────────────

enum FixtureStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}

// ── More fixture DTOs ──────────────────────────────────────────────────────────

class FixtureMultiRuleDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    #[Min(5)]
    #[Max(10)]
    public mixed $code = null;
}

class FixtureAssocOnlyDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsMap]
    public mixed $meta = null;
}

class FixtureBailDTO extends ValidatedDTO
{
    #[Bail]
    #[IsRequired]
    #[IsString]
    #[Min(5)]
    public mixed $code = null;
}

class FixturePasswordDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsString]
    public mixed $password = null;

    #[IsRequired]
    #[Equals('password')]
    public mixed $password_confirm = null;
}

class FixtureStatusDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsEnum(FixtureStatus::class)]
    public mixed $status = null;
}

class FixtureRoleDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsIn(['admin', 'user', 'guest'])]
    public mixed $role = null;

    #[IsRequired]
    #[IsNotIn(['banned'])]
    public mixed $tag = null;
}

class FixtureCustomDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsCustom('checkPositive', 'The :attribute must be positive')]
    public mixed $amount = null;

    public static function checkPositive(mixed $value, array &$_values, Property $_property): bool
    {
        return \is_numeric($value) && $value > 0;
    }
}

class FixtureTransformDTO extends ValidatedDTO
{
    #[IsRequired]
    #[Transform('trimValue')]
    #[Min(1)]
    public mixed $name = null;

    public static function trimValue(mixed $value, array $_values, Property $_property): mixed
    {
        return \is_string($value) ? \trim($value) : $value;
    }
}

// ── IsDistinct ────────────────────────────────────────────────────────────────

class FixtureDistinctDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsList]
    #[IsDistinct]
    public mixed $tags = null;
}

class FixtureDistinctStrictDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsList]
    #[IsDistinct(strict: true)]
    public mixed $items = null;
}

class FixtureDistinctIgnoreCaseDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsList]
    #[IsDistinct(ignoreCase: true)]
    public mixed $labels = null;
}

// ── IsFloat ────────────────────────────────────────────────────────────────────

class FixtureFloatDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsFloat]
    public mixed $price = null;
}

// ── IsNumeric ──────────────────────────────────────────────────────────────────

class FixtureNumericDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsNumeric]
    public mixed $value = null;
}

// ── IsPositive ─────────────────────────────────────────────────────────────────

class FixturePositiveDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsPositive]
    public mixed $amount = null;
}

// ── IsGreaterThan ──────────────────────────────────────────────────────────────

class FixtureGtDTO extends ValidatedDTO
{
    #[IsRequired]
    public mixed $min = null;

    #[IsRequired]
    #[IsGreaterThan('min')]
    public mixed $max = null;
}

// ── IsGreaterThanOrEqual ───────────────────────────────────────────────────────

class FixtureGteDTO extends ValidatedDTO
{
    #[IsRequired]
    public mixed $base = null;

    #[IsRequired]
    #[IsGreaterThanOrEqual('base')]
    public mixed $value = null;
}

// ── IsNotEmpty ─────────────────────────────────────────────────────────────────

class FixtureNotEmptyDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsNotEmpty]
    public mixed $text = null;
}

// ── IsRegex / IsNotRegex ──────────────────────────────────────────────────────

class FixtureRegexDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsRegex('/^\d{4}$/')]
    public mixed $year = null;
}

class FixtureNotRegexDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsNotRegex('/\d+/')]
    public mixed $word = null;
}

// ── IsUrl ──────────────────────────────────────────────────────────────────────

class FixtureUrlDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsUrl]
    public mixed $website = null;
}

// ── IsUUID ─────────────────────────────────────────────────────────────────────

class FixtureUUIDDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsUUID]
    public mixed $id = null;
}

class FixtureUUIDv4DTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsUUID(IsUUID::V4)]
    public mixed $id = null;
}

// ── IsAlpha ────────────────────────────────────────────────────────────────────

class FixtureAlphaDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsAlpha]
    public mixed $name = null;
}

// ── IsAlphaDash ────────────────────────────────────────────────────────────────

class FixtureAlphaDashDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsAlphaDash]
    public mixed $slug = null;
}

// ── IsAlphaNum ─────────────────────────────────────────────────────────────────

class FixtureAlphaNumDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsAlphaNum]
    public mixed $code = null;
}

// ── IsAlphaSpaces ──────────────────────────────────────────────────────────────

class FixtureAlphaSpacesDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsAlphaSpaces]
    public mixed $fullname = null;
}

// ── IsUppercase / IsLowercase ──────────────────────────────────────────────────

class FixtureUppercaseDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsUppercase]
    public mixed $code = null;
}

class FixtureLowercaseDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsLowercase]
    public mixed $slug = null;
}

// ── IsStartsWith ───────────────────────────────────────────────────────────────

class FixtureStartsWithDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsStartsWith('https')]
    public mixed $url = null;
}

class FixtureStartsWithMultiDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsStartsWith(['http://', 'https://'])]
    public mixed $url = null;
}

// ── IsDate ─────────────────────────────────────────────────────────────────────

class FixtureDateDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsDate]
    public mixed $date = null;
}

class FixtureDateFormatDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsDate('d/m/Y')]
    public mixed $date = null;
}

// ── IsDateString ───────────────────────────────────────────────────────────────

class FixtureDateStringDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsDateString]
    public mixed $created_at = null;
}

// ── IsIP / IsIPv4 / IsIPv6 ────────────────────────────────────────────────────

class FixtureIPDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsIP]
    public mixed $ip = null;
}

class FixtureIPv4DTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsIPv4]
    public mixed $ipv4 = null;
}

class FixtureIPv6DTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsIPv6]
    public mixed $ipv6 = null;
}

// ── IsMacAddress ───────────────────────────────────────────────────────────────

class FixtureMacAddressDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsMacAddress]
    public mixed $mac = null;
}

// ── IsLatLong / IsLatitude / IsLongitude ───────────────────────────────────────

class FixtureLatLongDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsLatLong]
    public mixed $coordinates = null;
}

class FixtureLatLngDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsLatitude]
    public mixed $lat = null;

    #[IsRequired]
    #[IsLongitude]
    public mixed $lng = null;
}

// ── IsCSSColor ─────────────────────────────────────────────────────────────────

class FixtureCSSColorDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsCSSColor]
    public mixed $color = null;
}

// ── IsHexadecimal ──────────────────────────────────────────────────────────────

class FixtureHexDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsHexadecimal]
    public mixed $hex = null;
}

// ── IsBase64 ───────────────────────────────────────────────────────────────────

class FixtureBase64DTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsBase64]
    public mixed $data = null;
}

// ── IsJson ─────────────────────────────────────────────────────────────────────

class FixtureJsonRuleDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsJson]
    public mixed $payload = null;
}

// ── IsAccepted ─────────────────────────────────────────────────────────────────

class FixtureAcceptedDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsAccepted]
    public mixed $terms = null;
}

// ── IsSize ─────────────────────────────────────────────────────────────────────

class FixtureSizeDTO extends ValidatedDTO
{
    #[IsRequired]
    #[IsSize(5)]
    public mixed $code = null;
}

// ──────────────────────────────────────────────────────────────────────────────
// Test cases
// ──────────────────────────────────────────────────────────────────────────────

class ValidatedDTOTest extends TestCase
{
    // ── Hydration ──────────────────────────────────────────────────────────────

    public function test_fromArray_hydrates_properties(): void
    {
        $dto = FixtureUserDTO::fromArray(['name' => 'John', 'email' => 'john@example.com']);

        $this->assertSame('John', $dto->name);
        $this->assertSame('john@example.com', $dto->email);
    }

    public function test_fromArray_sets_missing_fields_to_null(): void
    {
        $dto = FixtureUserDTO::fromArray([]);

        $this->assertNull($dto->name);
        $this->assertNull($dto->email);
    }

    public function test_fromArray_ignores_unknown_keys(): void
    {
        $dto = FixtureUserDTO::fromArray(['name' => 'John', 'email' => 'j@j.com', 'ghost' => 'ignored']);

        $this->assertFalse(isset($dto->ghost));
    }

    public function test_toArray_returns_all_declared_fields(): void
    {
        $dto = FixtureUserDTO::fromArray(['name' => 'John', 'email' => 'john@example.com']);

        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $dto->toArray());
    }

    public function test_toArray_includes_null_fields(): void
    {
        $dto = FixtureUserDTO::fromArray(['name' => 'John']);

        $array = $dto->toArray();
        $this->assertArrayHasKey('email', $array);
        $this->assertNull($array['email']);
    }

    // ── fromJson ───────────────────────────────────────────────────────────────

    public function test_fromJson_parses_valid_json(): void
    {
        $dto = FixtureUserDTO::fromJson('{"name":"Jane","email":"jane@example.com"}');

        $this->assertSame('Jane', $dto->name);
        $this->assertSame('jane@example.com', $dto->email);
    }

    public function test_fromJson_throws_on_invalid_json(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FixtureUserDTO::fromJson('not-a-json-string');
    }

    public function test_fromJson_throws_on_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FixtureUserDTO::fromJson('');
    }

    // ── Validation — pass ──────────────────────────────────────────────────────

    public function test_valid_data_does_not_throw(): void
    {
        $dto = FixtureUserDTO::fromArray([
            'name'  => 'John',
            'email' => 'john@example.com',
        ], validate: true);

        $this->assertInstanceOf(FixtureUserDTO::class, $dto);
    }

    public function test_optional_null_field_skips_validation(): void
    {
        $dto = FixtureOptionalAgeDTO::fromArray(['name' => 'John'], validate: true);

        $this->assertNull($dto->age);
    }

    public function test_optional_field_with_valid_value_passes_rules(): void
    {
        $dto = FixtureOptionalAgeDTO::fromArray(['name' => 'John', 'age' => 30], validate: true);

        $this->assertSame(30, $dto->age);
    }

    public function test_username_within_min_max_passes(): void
    {
        $dto = FixtureUsernameDTO::fromArray(['username' => 'john123'], validate: true);

        $this->assertSame('john123', $dto->username);
    }

    public function test_boolean_value_passes_is_boolean(): void
    {
        $dto = FixtureBooleanDTO::fromArray(['active' => 1], validate: true);

        $this->assertSame(1, $dto->active);
    }

    // ── Validation — fail ──────────────────────────────────────────────────────

    public function test_missing_required_field_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        FixtureUserDTO::fromArray(['name' => 'John'], validate: true);
    }

    public function test_null_required_field_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        FixtureUserDTO::fromArray(['name' => null, 'email' => null], validate: true);
    }

    public function test_validation_exception_status_code_is_422(): void
    {
        try {
            FixtureUserDTO::fromArray([], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_validation_exception_references_dto_class(): void
    {
        try {
            FixtureUserDTO::fromArray([], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(FixtureUserDTO::class, $e->getDTO());
        }
    }

    public function test_validation_exception_message_is_not_empty(): void
    {
        try {
            FixtureUserDTO::fromArray([], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_errors_contain_all_failed_fields(): void
    {
        try {
            FixtureUserDTO::fromArray(['name' => null, 'email' => null], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('name', $errors);
            $this->assertArrayHasKey('email', $errors);
        }
    }

    public function test_only_failed_fields_appear_in_errors(): void
    {
        try {
            FixtureUserDTO::fromArray(['name' => 'John', 'email' => 'invalid-email'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayNotHasKey('name', $errors);
        }
    }

    public function test_min_rule_fails_when_string_too_short(): void
    {
        try {
            FixtureUsernameDTO::fromArray(['username' => 'ab'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('username', $e->getErrors());
        }
    }

    public function test_max_rule_fails_when_string_too_long(): void
    {
        try {
            FixtureUsernameDTO::fromArray(['username' => 'toolongusername'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('username', $e->getErrors());
        }
    }

    public function test_invalid_email_produces_error_on_email_field(): void
    {
        try {
            FixtureUserDTO::fromArray(['name' => 'John', 'email' => 'not-an-email'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->getErrors());
        }
    }

    public function test_invalid_boolean_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);

        FixtureBooleanDTO::fromArray(['active' => 'yes'], validate: true);
    }

    // ── Nested DTO — IsMap ─────────────────────────────────────────────────────

    public function test_isMap_auto_hydrates_nested_dto_from_array(): void
    {
        $dto = FixtureUserWithAddressDTO::fromArray([
            'name'    => 'John',
            'address' => ['street' => '123 Main St', 'city' => 'Springfield'],
        ]);

        $this->assertInstanceOf(FixtureAddressDTO::class, $dto->address);
        $this->assertSame('123 Main St', $dto->address->street);
        $this->assertSame('Springfield', $dto->address->city);
    }

    public function test_isMap_does_not_rewrap_existing_dto_instance(): void
    {
        $address = FixtureAddressDTO::fromArray(['street' => 'Oak Ave', 'city' => 'Portland']);
        $dto = FixtureUserWithAddressDTO::fromArray(['name' => 'Jane', 'address' => $address]);

        $this->assertSame($address, $dto->address);
    }

    public function test_nested_dto_validation_errors_bubble_up(): void
    {
        try {
            FixtureUserWithAddressDTO::fromArray([
                'name'    => 'John',
                'address' => ['street' => null, 'city' => 'Springfield'],
            ], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('street', $e->getErrors());
        }
    }

    // ── IsList ─────────────────────────────────────────────────────────────────

    public function test_isList_accepts_sequential_array(): void
    {
        $dto = FixtureTagListDTO::fromArray(['tags' => ['php', 'pest', 'swoole']], validate: true);

        $this->assertSame(['php', 'pest', 'swoole'], $dto->tags);
    }

    public function test_isList_rejects_associative_array(): void
    {
        try {
            FixtureTagListDTO::fromArray(['tags' => ['lang' => 'php']], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tags', $e->getErrors());
        }
    }

    public function test_empty_array_fails_isRequired(): void
    {
        try {
            FixtureTagListDTO::fromArray(['tags' => []], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tags', $e->getErrors());
        }
    }

    // ── IsRequiredWhen ─────────────────────────────────────────────────────────

    public function test_isRequiredWhen_skips_when_condition_not_met(): void
    {
        $dto = FixtureConditionalDTO::fromArray(['type' => 'guest'], validate: true);

        $this->assertNull($dto->access_code);
    }

    public function test_isRequiredWhen_fails_when_condition_met_and_field_absent(): void
    {
        try {
            FixtureConditionalDTO::fromArray(['type' => 'admin'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('access_code', $e->getErrors());
        }
    }

    public function test_isRequiredWhen_passes_when_condition_met_and_field_provided(): void
    {
        $dto = FixtureConditionalDTO::fromArray([
            'type'        => 'admin',
            'access_code' => 'secret123',
        ], validate: true);

        $this->assertSame('secret123', $dto->access_code);
    }

    // ── ValidationException standalone ────────────────────────────────────────

    public function test_validation_exception_getErrors_returns_array(): void
    {
        $errors = ['name' => ['The name is required']];
        $ex = new ValidationException('The given data was invalid.', FixtureUserDTO::class, $errors);

        $this->assertSame($errors, $ex->getErrors());
    }

    public function test_validation_exception_getStatusCode_returns_422(): void
    {
        $ex = new ValidationException('msg', FixtureUserDTO::class, []);

        $this->assertSame(422, $ex->getStatusCode());
    }

    public function test_validation_exception_getDTO_returns_class_name(): void
    {
        $ex = new ValidationException('msg', FixtureUserDTO::class, []);

        $this->assertSame(FixtureUserDTO::class, $ex->getDTO());
    }

    // ── Schema caching ─────────────────────────────────────────────────────────

    public function test_schema_is_cached_in_static_property_after_compile(): void
    {
        FixtureUserDTO::fromArray(['name' => 'A', 'email' => 'a@a.com']);

        $prop = (new \ReflectionClass(ValidatedDTO::class))->getProperty('schemas');
        $schemas = $prop->getValue(null);

        $this->assertArrayHasKey(FixtureUserDTO::class, $schemas);
    }

    // ── IsList + Rule per item ─────────────────────────────────────────────────

    public function test_isList_with_rule_validates_each_item(): void
    {
        $dto = FixtureStringListDTO::fromArray(['items' => ['a', 'b', 'c']], validate: true);

        $this->assertSame(['a', 'b', 'c'], $dto->items);
    }

    public function test_isList_with_rule_fails_on_invalid_item(): void
    {
        try {
            FixtureStringListDTO::fromArray(['items' => ['a', 123, 'c']], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.1', $e->getErrors());
        }
    }

    // ── IsList + nested DTO per item ───────────────────────────────────────────

    public function test_isList_with_dto_validates_each_nested_dto(): void
    {
        $dto = FixtureDtoListDTO::fromArray([
            'rows' => [
                ['label' => 'first'],
                ['label' => 'second'],
            ],
        ], validate: true);

        $this->assertCount(2, $dto->rows);
        $this->assertInstanceOf(FixtureItemDTO::class, $dto->rows[0]);
        $this->assertSame('first', $dto->rows[0]->label);
    }

    public function test_isList_with_dto_fails_when_nested_item_invalid(): void
    {
        try {
            FixtureDtoListDTO::fromArray([
                'rows' => [
                    ['label' => 'ok'],
                    ['label' => null],
                ],
            ], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('label', $e->getErrors());
        }
    }

    // ── fromJson + validate ────────────────────────────────────────────────────

    public function test_fromJson_with_validate_passes_on_valid_data(): void
    {
        $dto = FixtureUserDTO::fromJson('{"name":"John","email":"john@example.com"}', validate: true);

        $this->assertSame('John', $dto->name);
    }

    public function test_fromJson_with_validate_throws_on_invalid_data(): void
    {
        $this->expectException(ValidationException::class);

        FixtureUserDTO::fromJson('{"name":"John","email":"bad"}', validate: true);
    }

    // ── Error message format ───────────────────────────────────────────────────

    public function test_error_message_contains_attribute_name(): void
    {
        try {
            FixtureUserDTO::fromArray(['name' => null, 'email' => 'x@x.com'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $message = $e->getErrors()['name'][0];
            $this->assertStringContainsString('name', $message);
        }
    }

    // ── toJson / only / except ─────────────────────────────────────────────────

    public function test_toJson_returns_json_string(): void
    {
        $dto = FixtureUserDTO::fromArray(['name' => 'John', 'email' => 'john@example.com']);

        $json = $dto->toJson();
        $decoded = \json_decode($json, true);

        $this->assertSame('John', $decoded['name']);
        $this->assertSame('john@example.com', $decoded['email']);
    }

    // ── Multiple errors per field ──────────────────────────────────────────────

    public function test_one_field_can_accumulate_multiple_rule_errors(): void
    {
        try {
            // code = null → fails IsRequired + IsString + Min(5)
            FixtureMultiRuleDTO::fromArray(['code' => null], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertGreaterThan(1, count($e->getErrors()['code']));
        }
    }

    public function test_each_rule_message_is_distinct(): void
    {
        try {
            FixtureMultiRuleDTO::fromArray(['code' => null], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $messages = $e->getErrors()['code'];
            $this->assertSame(count($messages), count(array_unique($messages)));
        }
    }

    // ── IsMap without dtoClass ─────────────────────────────────────────────────

    public function test_isMap_without_dto_class_accepts_assoc_array(): void
    {
        $dto = FixtureAssocOnlyDTO::fromArray(['meta' => ['key' => 'value']], validate: true);

        $this->assertSame(['key' => 'value'], $dto->meta);
    }

    public function test_isMap_without_dto_class_rejects_sequential_array(): void
    {
        try {
            FixtureAssocOnlyDTO::fromArray(['meta' => ['a', 'b']], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('meta', $e->getErrors());
        }
    }

    // ── Schema reuse across instances ──────────────────────────────────────────

    public function test_second_instance_reuses_compiled_schema(): void
    {
        FixtureUserDTO::fromArray(['name' => 'first', 'email' => 'a@a.com']);

        $prop = (new \ReflectionClass(ValidatedDTO::class))->getProperty('schemas');
        $schemasBefore = $prop->getValue(null);

        FixtureUserDTO::fromArray(['name' => 'second', 'email' => 'b@b.com']);

        $schemasAfter = $prop->getValue(null);

        $this->assertSame($schemasBefore[FixtureUserDTO::class], $schemasAfter[FixtureUserDTO::class]);
    }

    // ── Bail ───────────────────────────────────────────────────────────────────

    public function test_bail_stops_after_first_error(): void
    {
        try {
            FixtureBailDTO::fromArray(['code' => null], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            // Với Bail: IsRequired fail → dừng, IsString và Min không chạy → chỉ 1 lỗi
            $this->assertCount(1, $e->getErrors()['code']);
        }
    }

    public function test_without_bail_collects_all_errors(): void
    {
        try {
            // FixtureMultiRuleDTO không có Bail → tất cả rules đều chạy
            FixtureMultiRuleDTO::fromArray(['code' => null], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertGreaterThan(1, count($e->getErrors()['code']));
        }
    }

    // ── Equals (cross-field) ───────────────────────────────────────────────────

    public function test_equals_passes_when_fields_match(): void
    {
        $dto = FixturePasswordDTO::fromArray([
            'password'         => 'secret123',
            'password_confirm' => 'secret123',
        ], validate: true);

        $this->assertSame('secret123', $dto->password);
    }

    public function test_equals_fails_when_fields_differ(): void
    {
        try {
            FixturePasswordDTO::fromArray([
                'password'         => 'secret123',
                'password_confirm' => 'wrong',
            ], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password_confirm', $e->getErrors());
        }
    }

    // ── IsEnum ─────────────────────────────────────────────────────────────────

    public function test_isEnum_passes_valid_backed_value(): void
    {
        $dto = FixtureStatusDTO::fromArray(['status' => 'active'], validate: true);

        $this->assertSame('active', $dto->status);
    }

    public function test_isEnum_fails_invalid_value(): void
    {
        try {
            FixtureStatusDTO::fromArray(['status' => 'unknown'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->getErrors());
        }
    }

    // ── IsIn / IsNotIn ─────────────────────────────────────────────────────────

    public function test_isIn_passes_allowed_value(): void
    {
        $dto = FixtureRoleDTO::fromArray(['role' => 'admin', 'tag' => 'active'], validate: true);

        $this->assertSame('admin', $dto->role);
    }

    public function test_isIn_fails_disallowed_value(): void
    {
        try {
            FixtureRoleDTO::fromArray(['role' => 'superuser', 'tag' => 'active'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('role', $e->getErrors());
        }
    }

    public function test_isNotIn_fails_blacklisted_value(): void
    {
        try {
            FixtureRoleDTO::fromArray(['role' => 'admin', 'tag' => 'banned'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tag', $e->getErrors());
        }
    }

    // ── IsCustom ───────────────────────────────────────────────────────────────

    public function test_isCustom_passes_when_method_returns_true(): void
    {
        $dto = FixtureCustomDTO::fromArray(['amount' => 100], validate: true);

        $this->assertSame(100, $dto->amount);
    }

    public function test_isCustom_fails_when_method_returns_false(): void
    {
        try {
            FixtureCustomDTO::fromArray(['amount' => -5], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->getErrors());
        }
    }

    // ── Transform ──────────────────────────────────────────────────────────────

    public function test_transform_mutates_value_for_subsequent_rules(): void
    {
        // name = ' ' (whitespace): IsRequired pass → Transform trim → '' → Min(1) fail
        try {
            FixtureTransformDTO::fromArray(['name' => ' '], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->getErrors());
        }
    }

    public function test_transform_valid_value_passes_subsequent_rules(): void
    {
        $dto = FixtureTransformDTO::fromArray(['name' => '  hello  '], validate: true);

        $this->assertSame('  hello  ', $dto->name);
    }

    // ── IsDistinct ─────────────────────────────────────────────────────────────

    public function test_isDistinct_passes_unique_values(): void
    {
        $dto = FixtureDistinctDTO::fromArray(['tags' => ['php', 'go', 'rust']], validate: true);

        $this->assertSame(['php', 'go', 'rust'], $dto->tags);
    }

    public function test_isDistinct_fails_duplicate_values(): void
    {
        try {
            FixtureDistinctDTO::fromArray(['tags' => ['php', 'go', 'php']], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tags', $e->getErrors());
        }
    }

    public function test_isDistinct_non_strict_treats_coercible_values_as_duplicate(): void
    {
        // '1' và 1 collapse thành cùng key trong array_flip (PHP convert numeric string → int key)
        try {
            FixtureDistinctDTO::fromArray(['tags' => ['1', 1]], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('tags', $e->getErrors());
        }
    }

    public function test_isDistinct_strict_distinguishes_type_coercion(): void
    {
        // strict mode: '1' !== 1 → không phải duplicate
        $dto = FixtureDistinctStrictDTO::fromArray(['items' => ['1', 1]], validate: true);

        $this->assertCount(2, $dto->items);
    }

    public function test_isDistinct_ignoreCase_treats_same_case_as_duplicate(): void
    {
        try {
            FixtureDistinctIgnoreCaseDTO::fromArray(['labels' => ['PHP', 'php']], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('labels', $e->getErrors());
        }
    }

    public function test_isDistinct_ignoreCase_passes_different_values(): void
    {
        $dto = FixtureDistinctIgnoreCaseDTO::fromArray(['labels' => ['PHP', 'Go']], validate: true);

        $this->assertCount(2, $dto->labels);
    }

    // ── IsFloat ────────────────────────────────────────────────────────────────

    public function test_isFloat_passes_float_value(): void
    {
        $dto = FixtureFloatDTO::fromArray(['price' => 3.14], validate: true);

        $this->assertSame(3.14, $dto->price);
    }

    public function test_isFloat_passes_integer(): void
    {
        $dto = FixtureFloatDTO::fromArray(['price' => 10], validate: true);

        $this->assertSame(10, $dto->price);
    }

    public function test_isFloat_passes_numeric_string(): void
    {
        $dto = FixtureFloatDTO::fromArray(['price' => '9.99'], validate: true);

        $this->assertSame('9.99', $dto->price);
    }

    public function test_isFloat_fails_non_numeric_string(): void
    {
        try {
            FixtureFloatDTO::fromArray(['price' => 'abc'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('price', $e->getErrors());
        }
    }

    // ── IsNumeric ──────────────────────────────────────────────────────────────

    public function test_isNumeric_passes_integer(): void
    {
        $dto = FixtureNumericDTO::fromArray(['value' => 42], validate: true);

        $this->assertSame(42, $dto->value);
    }

    public function test_isNumeric_passes_float(): void
    {
        $dto = FixtureNumericDTO::fromArray(['value' => 3.14], validate: true);

        $this->assertSame(3.14, $dto->value);
    }

    public function test_isNumeric_passes_numeric_string(): void
    {
        $dto = FixtureNumericDTO::fromArray(['value' => '100'], validate: true);

        $this->assertSame('100', $dto->value);
    }

    public function test_isNumeric_fails_non_numeric_string(): void
    {
        try {
            FixtureNumericDTO::fromArray(['value' => 'abc'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('value', $e->getErrors());
        }
    }

    // ── IsPositive ─────────────────────────────────────────────────────────────

    public function test_isPositive_passes_positive_number(): void
    {
        $dto = FixturePositiveDTO::fromArray(['amount' => 100], validate: true);

        $this->assertSame(100, $dto->amount);
    }

    public function test_isPositive_fails_zero(): void
    {
        try {
            FixturePositiveDTO::fromArray(['amount' => 0], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->getErrors());
        }
    }

    public function test_isPositive_fails_negative(): void
    {
        try {
            FixturePositiveDTO::fromArray(['amount' => -5], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->getErrors());
        }
    }

    // ── IsGreaterThan ──────────────────────────────────────────────────────────

    public function test_isGreaterThan_passes_when_larger(): void
    {
        $dto = FixtureGtDTO::fromArray(['min' => 5, 'max' => 10], validate: true);

        $this->assertSame(10, $dto->max);
    }

    public function test_isGreaterThan_fails_when_equal(): void
    {
        try {
            FixtureGtDTO::fromArray(['min' => 10, 'max' => 10], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('max', $e->getErrors());
        }
    }

    public function test_isGreaterThan_fails_when_smaller(): void
    {
        try {
            FixtureGtDTO::fromArray(['min' => 10, 'max' => 5], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('max', $e->getErrors());
        }
    }

    // ── IsGreaterThanOrEqual ───────────────────────────────────────────────────

    public function test_isGreaterThanOrEqual_passes_when_equal(): void
    {
        $dto = FixtureGteDTO::fromArray(['base' => 10, 'value' => 10], validate: true);

        $this->assertSame(10, $dto->value);
    }

    public function test_isGreaterThanOrEqual_passes_when_greater(): void
    {
        $dto = FixtureGteDTO::fromArray(['base' => 5, 'value' => 10], validate: true);

        $this->assertSame(10, $dto->value);
    }

    public function test_isGreaterThanOrEqual_fails_when_smaller(): void
    {
        try {
            FixtureGteDTO::fromArray(['base' => 10, 'value' => 5], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('value', $e->getErrors());
        }
    }

    // ── IsNotEmpty ─────────────────────────────────────────────────────────────

    public function test_isNotEmpty_passes_non_empty_string(): void
    {
        $dto = FixtureNotEmptyDTO::fromArray(['text' => 'hello'], validate: true);

        $this->assertSame('hello', $dto->text);
    }

    public function test_isNotEmpty_fails_empty_string(): void
    {
        try {
            FixtureNotEmptyDTO::fromArray(['text' => ''], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('text', $e->getErrors());
        }
    }

    public function test_isNotEmpty_fails_whitespace_only(): void
    {
        try {
            FixtureNotEmptyDTO::fromArray(['text' => '   '], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('text', $e->getErrors());
        }
    }

    // ── IsRegex ────────────────────────────────────────────────────────────────

    public function test_isRegex_passes_matching_value(): void
    {
        $dto = FixtureRegexDTO::fromArray(['year' => '2024'], validate: true);

        $this->assertSame('2024', $dto->year);
    }

    public function test_isRegex_fails_non_matching_value(): void
    {
        try {
            FixtureRegexDTO::fromArray(['year' => 'abcd'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('year', $e->getErrors());
        }
    }

    public function test_isRegex_fails_partial_match(): void
    {
        try {
            FixtureRegexDTO::fromArray(['year' => '12345'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('year', $e->getErrors());
        }
    }

    // ── IsNotRegex ─────────────────────────────────────────────────────────────

    public function test_isNotRegex_passes_non_matching_value(): void
    {
        $dto = FixtureNotRegexDTO::fromArray(['word' => 'hello'], validate: true);

        $this->assertSame('hello', $dto->word);
    }

    public function test_isNotRegex_fails_matching_value(): void
    {
        try {
            FixtureNotRegexDTO::fromArray(['word' => 'hello123'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('word', $e->getErrors());
        }
    }

    // ── IsUrl ──────────────────────────────────────────────────────────────────

    public function test_isUrl_passes_valid_url(): void
    {
        $dto = FixtureUrlDTO::fromArray(['website' => 'https://example.com'], validate: true);

        $this->assertSame('https://example.com', $dto->website);
    }

    public function test_isUrl_fails_invalid_url(): void
    {
        try {
            FixtureUrlDTO::fromArray(['website' => 'not-a-url'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('website', $e->getErrors());
        }
    }

    public function test_isUrl_fails_url_without_scheme(): void
    {
        try {
            FixtureUrlDTO::fromArray(['website' => 'example.com'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('website', $e->getErrors());
        }
    }

    // ── IsUUID ─────────────────────────────────────────────────────────────────

    public function test_isUUID_passes_valid_uuid(): void
    {
        $dto = FixtureUUIDDTO::fromArray(['id' => '550e8400-e29b-41d4-a716-446655440000'], validate: true);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $dto->id);
    }

    public function test_isUUID_fails_invalid_uuid(): void
    {
        try {
            FixtureUUIDDTO::fromArray(['id' => 'not-a-uuid'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('id', $e->getErrors());
        }
    }

    public function test_isUUID_v4_passes_valid_v4(): void
    {
        $dto = FixtureUUIDv4DTO::fromArray(['id' => '550e8400-e29b-41d4-a716-446655440000'], validate: true);

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $dto->id);
    }

    public function test_isUUID_v4_fails_v1_uuid(): void
    {
        try {
            FixtureUUIDv4DTO::fromArray(['id' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('id', $e->getErrors());
        }
    }

    // ── IsAlpha ────────────────────────────────────────────────────────────────

    public function test_isAlpha_passes_alpha_string(): void
    {
        $dto = FixtureAlphaDTO::fromArray(['name' => 'JohnDoe'], validate: true);

        $this->assertSame('JohnDoe', $dto->name);
    }

    public function test_isAlpha_fails_with_numbers(): void
    {
        try {
            FixtureAlphaDTO::fromArray(['name' => 'John123'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->getErrors());
        }
    }

    public function test_isAlpha_fails_with_spaces(): void
    {
        try {
            FixtureAlphaDTO::fromArray(['name' => 'John Doe'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->getErrors());
        }
    }

    // ── IsAlphaDash ────────────────────────────────────────────────────────────

    public function test_isAlphaDash_passes_with_dash(): void
    {
        $dto = FixtureAlphaDashDTO::fromArray(['slug' => 'my-slug'], validate: true);

        $this->assertSame('my-slug', $dto->slug);
    }

    public function test_isAlphaDash_passes_with_underscore(): void
    {
        $dto = FixtureAlphaDashDTO::fromArray(['slug' => 'my_slug'], validate: true);

        $this->assertSame('my_slug', $dto->slug);
    }

    public function test_isAlphaDash_fails_with_spaces(): void
    {
        try {
            FixtureAlphaDashDTO::fromArray(['slug' => 'my slug'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->getErrors());
        }
    }

    // ── IsAlphaNum ─────────────────────────────────────────────────────────────

    public function test_isAlphaNum_passes_alphanumeric(): void
    {
        $dto = FixtureAlphaNumDTO::fromArray(['code' => 'abc123'], validate: true);

        $this->assertSame('abc123', $dto->code);
    }

    public function test_isAlphaNum_fails_with_special_chars(): void
    {
        try {
            FixtureAlphaNumDTO::fromArray(['code' => 'abc-123'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->getErrors());
        }
    }

    // ── IsAlphaSpaces ──────────────────────────────────────────────────────────

    public function test_isAlphaSpaces_passes_alpha_with_spaces(): void
    {
        $dto = FixtureAlphaSpacesDTO::fromArray(['fullname' => 'John Doe'], validate: true);

        $this->assertSame('John Doe', $dto->fullname);
    }

    public function test_isAlphaSpaces_fails_with_numbers(): void
    {
        try {
            FixtureAlphaSpacesDTO::fromArray(['fullname' => 'John 123'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fullname', $e->getErrors());
        }
    }

    // ── IsUppercase ────────────────────────────────────────────────────────────

    public function test_isUppercase_passes_uppercase_string(): void
    {
        $dto = FixtureUppercaseDTO::fromArray(['code' => 'ADMIN'], validate: true);

        $this->assertSame('ADMIN', $dto->code);
    }

    public function test_isUppercase_fails_lowercase_string(): void
    {
        try {
            FixtureUppercaseDTO::fromArray(['code' => 'admin'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->getErrors());
        }
    }

    public function test_isUppercase_fails_mixed_case(): void
    {
        try {
            FixtureUppercaseDTO::fromArray(['code' => 'Admin'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->getErrors());
        }
    }

    // ── IsLowercase ────────────────────────────────────────────────────────────

    public function test_isLowercase_passes_lowercase_string(): void
    {
        $dto = FixtureLowercaseDTO::fromArray(['slug' => 'hello-world'], validate: true);

        $this->assertSame('hello-world', $dto->slug);
    }

    public function test_isLowercase_fails_uppercase_string(): void
    {
        try {
            FixtureLowercaseDTO::fromArray(['slug' => 'Hello'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->getErrors());
        }
    }

    // ── IsStartsWith ───────────────────────────────────────────────────────────

    public function test_isStartsWith_passes_matching_prefix(): void
    {
        $dto = FixtureStartsWithDTO::fromArray(['url' => 'https://example.com'], validate: true);

        $this->assertSame('https://example.com', $dto->url);
    }

    public function test_isStartsWith_fails_non_matching_prefix(): void
    {
        try {
            FixtureStartsWithDTO::fromArray(['url' => 'ftp://example.com'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('url', $e->getErrors());
        }
    }

    public function test_isStartsWith_multi_prefix_passes_any_match(): void
    {
        $dto = FixtureStartsWithMultiDTO::fromArray(['url' => 'http://example.com'], validate: true);

        $this->assertSame('http://example.com', $dto->url);
    }

    public function test_isStartsWith_multi_prefix_fails_no_match(): void
    {
        try {
            FixtureStartsWithMultiDTO::fromArray(['url' => 'ftp://example.com'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('url', $e->getErrors());
        }
    }

    // ── IsDate ─────────────────────────────────────────────────────────────────

    public function test_isDate_passes_valid_date(): void
    {
        $dto = FixtureDateDTO::fromArray(['date' => '2024-01-15'], validate: true);

        $this->assertSame('2024-01-15', $dto->date);
    }

    public function test_isDate_fails_invalid_date(): void
    {
        try {
            FixtureDateDTO::fromArray(['date' => 'not-a-date'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('date', $e->getErrors());
        }
    }

    public function test_isDate_fails_wrong_format(): void
    {
        try {
            FixtureDateDTO::fromArray(['date' => '15/01/2024'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('date', $e->getErrors());
        }
    }

    public function test_isDate_custom_format_passes(): void
    {
        $dto = FixtureDateFormatDTO::fromArray(['date' => '15/01/2024'], validate: true);

        $this->assertSame('15/01/2024', $dto->date);
    }

    // ── IsDateString ───────────────────────────────────────────────────────────

    public function test_isDateString_passes_date_only(): void
    {
        $dto = FixtureDateStringDTO::fromArray(['created_at' => '2024-01-15'], validate: true);

        $this->assertSame('2024-01-15', $dto->created_at);
    }

    public function test_isDateString_passes_full_datetime_with_timezone(): void
    {
        $dto = FixtureDateStringDTO::fromArray(['created_at' => '2024-01-15T10:30:00Z'], validate: true);

        $this->assertSame('2024-01-15T10:30:00Z', $dto->created_at);
    }

    public function test_isDateString_passes_datetime_with_offset(): void
    {
        $dto = FixtureDateStringDTO::fromArray(['created_at' => '2024-01-15T10:30:00+07:00'], validate: true);

        $this->assertSame('2024-01-15T10:30:00+07:00', $dto->created_at);
    }

    public function test_isDateString_fails_invalid_string(): void
    {
        try {
            FixtureDateStringDTO::fromArray(['created_at' => 'not-a-date'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('created_at', $e->getErrors());
        }
    }

    // ── IsIP ───────────────────────────────────────────────────────────────────

    public function test_isIP_passes_ipv4_address(): void
    {
        $dto = FixtureIPDTO::fromArray(['ip' => '192.168.1.1'], validate: true);

        $this->assertSame('192.168.1.1', $dto->ip);
    }

    public function test_isIP_passes_ipv6_address(): void
    {
        $dto = FixtureIPDTO::fromArray(['ip' => '2001:db8::1'], validate: true);

        $this->assertSame('2001:db8::1', $dto->ip);
    }

    public function test_isIP_fails_invalid_address(): void
    {
        try {
            FixtureIPDTO::fromArray(['ip' => '999.999.999.999'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ip', $e->getErrors());
        }
    }

    // ── IsIPv4 ─────────────────────────────────────────────────────────────────

    public function test_isIPv4_passes_valid_ipv4(): void
    {
        $dto = FixtureIPv4DTO::fromArray(['ipv4' => '10.0.0.1'], validate: true);

        $this->assertSame('10.0.0.1', $dto->ipv4);
    }

    public function test_isIPv4_fails_ipv6_address(): void
    {
        try {
            FixtureIPv4DTO::fromArray(['ipv4' => '2001:db8::1'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ipv4', $e->getErrors());
        }
    }

    // ── IsIPv6 ─────────────────────────────────────────────────────────────────

    public function test_isIPv6_passes_valid_ipv6(): void
    {
        $dto = FixtureIPv6DTO::fromArray(['ipv6' => '2001:db8::1'], validate: true);

        $this->assertSame('2001:db8::1', $dto->ipv6);
    }

    public function test_isIPv6_fails_ipv4_address(): void
    {
        try {
            FixtureIPv6DTO::fromArray(['ipv6' => '192.168.1.1'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ipv6', $e->getErrors());
        }
    }

    // ── IsMacAddress ───────────────────────────────────────────────────────────

    public function test_isMacAddress_passes_colon_format(): void
    {
        $dto = FixtureMacAddressDTO::fromArray(['mac' => '00:1A:2B:3C:4D:5E'], validate: true);

        $this->assertSame('00:1A:2B:3C:4D:5E', $dto->mac);
    }

    public function test_isMacAddress_passes_hyphen_format(): void
    {
        $dto = FixtureMacAddressDTO::fromArray(['mac' => '00-1A-2B-3C-4D-5E'], validate: true);

        $this->assertSame('00-1A-2B-3C-4D-5E', $dto->mac);
    }

    public function test_isMacAddress_fails_invalid_format(): void
    {
        try {
            FixtureMacAddressDTO::fromArray(['mac' => '00:1A:2B:3C:4D'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('mac', $e->getErrors());
        }
    }

    // ── IsLatLong ──────────────────────────────────────────────────────────────

    public function test_isLatLong_passes_valid_coordinates(): void
    {
        $dto = FixtureLatLongDTO::fromArray(['coordinates' => '10.762622,106.660172'], validate: true);

        $this->assertSame('10.762622,106.660172', $dto->coordinates);
    }

    public function test_isLatLong_fails_invalid_coordinates(): void
    {
        try {
            FixtureLatLongDTO::fromArray(['coordinates' => '200.0,200.0'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('coordinates', $e->getErrors());
        }
    }

    // ── IsLatitude ─────────────────────────────────────────────────────────────

    public function test_isLatitude_passes_valid_latitude(): void
    {
        $dto = FixtureLatLngDTO::fromArray(['lat' => 10.762622, 'lng' => 106.660172], validate: true);

        $this->assertSame(10.762622, $dto->lat);
    }

    public function test_isLatitude_fails_out_of_range(): void
    {
        try {
            FixtureLatLngDTO::fromArray(['lat' => 91.0, 'lng' => 0.0], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lat', $e->getErrors());
        }
    }

    // ── IsLongitude ────────────────────────────────────────────────────────────

    public function test_isLongitude_passes_valid_longitude(): void
    {
        $dto = FixtureLatLngDTO::fromArray(['lat' => 0.0, 'lng' => 106.660172], validate: true);

        $this->assertSame(106.660172, $dto->lng);
    }

    public function test_isLongitude_fails_out_of_range(): void
    {
        try {
            FixtureLatLngDTO::fromArray(['lat' => 0.0, 'lng' => 181.0], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lng', $e->getErrors());
        }
    }

    // ── IsCSSColor ─────────────────────────────────────────────────────────────

    public function test_isCSSColor_passes_hex_color(): void
    {
        $dto = FixtureCSSColorDTO::fromArray(['color' => '#FF5733'], validate: true);

        $this->assertSame('#FF5733', $dto->color);
    }

    public function test_isCSSColor_passes_rgb_color(): void
    {
        $dto = FixtureCSSColorDTO::fromArray(['color' => 'rgb(255, 87, 51)'], validate: true);

        $this->assertSame('rgb(255, 87, 51)', $dto->color);
    }

    public function test_isCSSColor_passes_named_color(): void
    {
        $dto = FixtureCSSColorDTO::fromArray(['color' => 'red'], validate: true);

        $this->assertSame('red', $dto->color);
    }

    public function test_isCSSColor_fails_invalid_color(): void
    {
        try {
            FixtureCSSColorDTO::fromArray(['color' => 'notacolor'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('color', $e->getErrors());
        }
    }

    // ── IsHexadecimal ──────────────────────────────────────────────────────────

    public function test_isHexadecimal_passes_valid_hex(): void
    {
        $dto = FixtureHexDTO::fromArray(['hex' => 'FF5733'], validate: true);

        $this->assertSame('FF5733', $dto->hex);
    }

    public function test_isHexadecimal_passes_hex_with_0x_prefix(): void
    {
        $dto = FixtureHexDTO::fromArray(['hex' => '0xFF5733'], validate: true);

        $this->assertSame('0xFF5733', $dto->hex);
    }

    public function test_isHexadecimal_fails_non_hex(): void
    {
        try {
            FixtureHexDTO::fromArray(['hex' => 'GHIJKL'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('hex', $e->getErrors());
        }
    }

    // ── IsBase64 ───────────────────────────────────────────────────────────────

    public function test_isBase64_passes_valid_base64(): void
    {
        $encoded = \base64_encode('Hello World');
        $dto = FixtureBase64DTO::fromArray(['data' => $encoded], validate: true);

        $this->assertSame($encoded, $dto->data);
    }

    public function test_isBase64_fails_invalid_base64(): void
    {
        try {
            FixtureBase64DTO::fromArray(['data' => 'not!base64@value'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('data', $e->getErrors());
        }
    }

    // ── IsJson ─────────────────────────────────────────────────────────────────

    public function test_isJson_passes_valid_json_object(): void
    {
        $dto = FixtureJsonRuleDTO::fromArray(['payload' => '{"key":"value"}'], validate: true);

        $this->assertSame('{"key":"value"}', $dto->payload);
    }

    public function test_isJson_passes_valid_json_array(): void
    {
        $dto = FixtureJsonRuleDTO::fromArray(['payload' => '[1,2,3]'], validate: true);

        $this->assertSame('[1,2,3]', $dto->payload);
    }

    public function test_isJson_fails_invalid_json(): void
    {
        try {
            FixtureJsonRuleDTO::fromArray(['payload' => '{invalid}'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payload', $e->getErrors());
        }
    }

    public function test_isJson_fails_empty_string(): void
    {
        try {
            FixtureJsonRuleDTO::fromArray(['payload' => ''], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payload', $e->getErrors());
        }
    }

    // ── IsAccepted ─────────────────────────────────────────────────────────────

    public function test_isAccepted_passes_yes(): void
    {
        $dto = FixtureAcceptedDTO::fromArray(['terms' => 'yes'], validate: true);

        $this->assertSame('yes', $dto->terms);
    }

    public function test_isAccepted_passes_true_bool(): void
    {
        $dto = FixtureAcceptedDTO::fromArray(['terms' => true], validate: true);

        $this->assertTrue($dto->terms);
    }

    public function test_isAccepted_passes_1_int(): void
    {
        $dto = FixtureAcceptedDTO::fromArray(['terms' => 1], validate: true);

        $this->assertSame(1, $dto->terms);
    }

    public function test_isAccepted_passes_on(): void
    {
        $dto = FixtureAcceptedDTO::fromArray(['terms' => 'on'], validate: true);

        $this->assertSame('on', $dto->terms);
    }

    public function test_isAccepted_fails_no(): void
    {
        try {
            FixtureAcceptedDTO::fromArray(['terms' => 'no'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('terms', $e->getErrors());
        }
    }

    public function test_isAccepted_fails_false_bool(): void
    {
        try {
            FixtureAcceptedDTO::fromArray(['terms' => false], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('terms', $e->getErrors());
        }
    }

    // ── IsSize ─────────────────────────────────────────────────────────────────

    public function test_isSize_passes_string_with_exact_length(): void
    {
        $dto = FixtureSizeDTO::fromArray(['code' => 'hello'], validate: true);

        $this->assertSame('hello', $dto->code);
    }

    public function test_isSize_fails_string_with_wrong_length(): void
    {
        try {
            FixtureSizeDTO::fromArray(['code' => 'hi'], validate: true);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->getErrors());
        }
    }

    public function test_isSize_passes_integer_equal_to_size(): void
    {
        $dto = FixtureSizeDTO::fromArray(['code' => 5], validate: true);

        $this->assertSame(5, $dto->code);
    }

    public function test_isSize_passes_array_with_exact_count(): void
    {
        $dto = FixtureSizeDTO::fromArray(['code' => [1, 2, 3, 4, 5]], validate: true);

        $this->assertCount(5, $dto->code);
    }

    // ── IsRequiredWhen with closure (direct rule test) ─────────────────────────

    public function test_isRequiredWhen_closure_true_fails_null_field(): void
    {
        $property = new Property('extra');
        $values = [];
        $rule = new IsRequiredWhen(fn() => true);
        $value = null;

        $this->assertFalse($rule->check($value, $values, $property));
    }

    public function test_isRequiredWhen_closure_false_skips_null_field(): void
    {
        $property = new Property('extra');
        $values = [];
        $rule = new IsRequiredWhen(fn() => false);
        $value = null;

        $this->assertTrue($rule->check($value, $values, $property));
    }

    public function test_isRequiredWhen_closure_true_passes_when_field_present(): void
    {
        $property = new Property('extra');
        $values = ['extra' => 'value'];
        $rule = new IsRequiredWhen(fn() => true);
        $value = 'value';

        $this->assertTrue($rule->check($value, $values, $property));
    }

}
