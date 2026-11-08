<?php

namespace Ryra\Core\ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiParameter
{
    public function __construct(
        protected string $name,
        protected string $in = 'query', // query, header, path, cookie
        protected ?string $description = null,
        protected bool $required = false,
        protected array $schema = ['type' => 'string'],
        protected mixed $example = null
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getIn(): string
    {
        return $this->in;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getExample(): mixed
    {
        return $this->example;
    }
}
