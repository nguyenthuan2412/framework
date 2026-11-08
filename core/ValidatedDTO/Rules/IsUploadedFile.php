<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Traits\FileTrait;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsUploadedFile extends Rule
{
    use FileTrait;

    public function __construct(
        protected ?int $maxSize = null,
        protected ?int $minSize = null,
        protected array $allowedTypes = [],
        protected string $message = 'The :attribute is not valid uploaded file'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (!$this->isUploadedFile($value)) {
            return false;
        }

        if ($value['error']) {
            return false;
        }

        return true;
    }
}