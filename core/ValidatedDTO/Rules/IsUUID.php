<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Uid\UuidV8;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsUUID extends Rule
{
    const V1 = 1;
    const V3 = 3;
    const V4 = 4;
    const V5 = 5;
    const V6 = 6;
    const V7 = 7;
    const V8 = 8;

    public function __construct(
        protected ?int $version = null,
        protected string $message = 'The :attribute must be a valid UUID.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!\is_string($value) || !Uuid::isValid($value)) {
            return false;
        }

        if ($this->version === null) {
            return true;
        }

        $uuid = Uuid::fromString($value);

        return match ($this->version) {
            self::V1 => $uuid instanceof UuidV1,
            self::V3 => $uuid instanceof UuidV3,
            self::V4 => $uuid instanceof UuidV4,
            self::V5 => $uuid instanceof UuidV5,
            self::V6 => $uuid instanceof UuidV6,
            self::V7 => $uuid instanceof UuidV7,
            self::V8 => $uuid instanceof UuidV8,
            default  => false,
        };
    }
}
