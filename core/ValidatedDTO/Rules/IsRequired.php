<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsRequired extends Rule
{
    public function __construct(protected string $message = 'The :attribute is required') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if ($property->hasRule(IsFile::class) || $property->hasRule(IsImage::class)) {
            return $this->isUploadedFile($value);
        }

        if (\is_string($value)) {
            return \mb_strlen($value, 'UTF-8') > 0;
        }

        if (\is_array($value)) {
            return \count($value) > 0;
        }

        return !\is_null($value);
    }
}