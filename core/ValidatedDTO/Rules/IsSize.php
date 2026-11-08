<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Traits\FileTrait;
use Ryra\Core\ValidatedDTO\Traits\SizeTrait;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsSize extends Rule
{
    use FileTrait;
    use SizeTrait;

    protected float $rawSize;

    public function __construct(
        protected string|float $size,
        protected string $message = 'The :attribute must be smaller than :size.'
    ) {
        $this->rawSize = $this->getKilobytesSize($size);
    }

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if ($this->isUploadedFile($value)) {
            $fileSize = $value->getSize() / 1024;
            return $fileSize <= $this->rawSize;
        }

        if (\is_int($value) || ($value !== null && \is_string($value) && \ctype_digit($value))) {
            return $value == $this->size;
        }

        if (\is_string($value)) {
            return \mb_strlen($value) == $this->size;
        }

        if (\is_array($value)) {
            return \count($value) == $this->size;
        }

        return true;
    }
}