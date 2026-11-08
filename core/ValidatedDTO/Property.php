<?php

namespace Ryra\Core\ValidatedDTO;

use ReflectionClass;
use Ryra\Core\ValidatedDTO\Rules\Bail;
use Ryra\Core\ValidatedDTO\Rules\IsOptional;

class Property
{
    protected bool $hasNullable;

    protected bool $hasBail;

    protected array $rules = [];

    public function __construct(
        public readonly string $name,
        array $rules = []
    ) {
        $this->setRules($rules);
    }

    public function hasNullable(): bool
    {
        return $this->hasNullable;
    }

    public function hasBail(): bool
    {
        return $this->hasBail;
    }

    public function hasRule(string $rule): bool
    {
        return isset($this->rules[$rule]);
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @param class-string<T> $rule
     * @return T|null
     */
    public function getRule(string $rule): ?Rule
    {
        return $this->rules[$rule] ?? null;
    }

    public function setRules(array $rules): void
    {
        $this->hasNullable = isset($rules[IsOptional::class]);
        $this->hasBail = isset($rules[Bail::class]);
        unset($rules[IsOptional::class]);
        unset($rules[Bail::class]);

        foreach ($rules as $rule) {
            $ruleName = (new ReflectionClass($rule))->getName();
            $this->rules[$ruleName] = $rule;
        }
    }
}