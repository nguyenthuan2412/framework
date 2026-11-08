<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsExtension extends Rule
{
    protected array $allowedExtensions;

    public function __construct(
        array $allowedExtensions,
        protected string $message = 'The :attribute must be a :allowedExtensions file.'
    )
    {
        $this->allowedExtensions = \array_map(function ($ext) {
            return \strtolower(\ltrim($ext, '.'));
        }, $allowedExtensions);
    }

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        $ext = \strtolower(\pathinfo($value, PATHINFO_EXTENSION));
        return $ext && \in_array($ext, $this->allowedExtensions) ? true : false;
    }

    protected function renderAllowedExtensionsText(): string
    {
        return \implode(',', $this->allowedExtensions);
    }
}