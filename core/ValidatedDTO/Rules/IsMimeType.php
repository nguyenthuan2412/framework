<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Ryra\Core\ValidatedDTO\Traits\FileTrait;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsMimeType extends Rule
{
    use FileTrait;

    public function __construct(
        protected array $allowMimeTypes = [],
        protected string $message = 'The :attribute must be a file of type: :allowMimeTypes.'
    ) {}

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (\is_string($value)) {
            return \in_array(\mime_content_type($value), $this->allowMimeTypes);
        }

        if ($this->isUploadedFile($value)) {
            return \in_array($value->getClientMimeType(), $this->allowMimeTypes);
        }

        return false;
    }

    protected function renderAllowMimeTypesText(): string
    {
        return \implode(',', $this->allowMimeTypes);
    }
}