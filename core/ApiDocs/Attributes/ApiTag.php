<?php

namespace Ryra\Core\ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ApiTag
{
    public function __construct(
        protected string $name,
        protected ?string $description = null
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
