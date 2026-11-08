<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Traits\FileTrait;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsFile extends Rule
{
    use FileTrait;

    public function __construct(protected string $message = 'The :attribute must be file.') {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        return $this->isUploadedFile($value);
    }
}