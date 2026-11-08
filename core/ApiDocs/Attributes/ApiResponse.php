<?php

namespace Ryra\Core\ApiDocs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiResponse
{
    public function __construct(
        protected int $statusCode = 200,
        protected string $description = 'Successful response',
        protected ?array $schema = null,
        protected ?array $example = null
    ) {}

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSchema(): ?array
    {
        return $this->schema;
    }

    public function getExample(): ?array
    {
        return $this->example;
    }
}
