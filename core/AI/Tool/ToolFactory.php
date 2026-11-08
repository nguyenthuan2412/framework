<?php

namespace Ryra\Core\AI\Tool;

use BackedEnum;
use ReflectionAttribute;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionParameter;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\Tool\Attributes\Tool as ToolAttribute;
use Ryra\Core\AI\Tool\Attributes\ToolParam;
use Ryra\Core\Support\Str;

class ToolFactory
{
    /**
     * Quét các method có attribute #[Tool] trên một object và dựng ToolDefinition,
     * schema tham số được suy ra từ kiểu dữ liệu khai báo trong PHP.
     *
     * @return array<int, ToolDefinition>
     */
    public static function fromObject(object $instance): array
    {
        $tools = [];

        foreach ((new ReflectionObject($instance))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attribute = $method->getAttributes(ToolAttribute::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

            if ($attribute === null) {
                continue;
            }

            $tools[] = static::fromMethod($instance, $method, $attribute->newInstance());
        }

        return $tools;
    }

    protected static function fromMethod(object $instance, ReflectionMethod $method, ToolAttribute $attribute): ToolDefinition
    {
        $parameters = $method->getParameters();

        return new ToolDefinition(
            $attribute->name ?? Str::snake($method->getName()),
            $attribute->description,
            static::buildSchema($parameters),
            static function (array $arguments) use ($instance, $method, $parameters): mixed {
                return $method->invokeArgs($instance, static::bindArguments($parameters, $arguments));
            },
            true,
        );
    }

    /**
     * @param  array<int, ReflectionParameter> $parameters
     * @return array<string, mixed>
     */
    protected static function buildSchema(array $parameters): array
    {
        $properties = [];
        $required = [];

        foreach ($parameters as $parameter) {
            $meta = $parameter->getAttributes(ToolParam::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
            $meta = $meta?->newInstance();

            $property = static::typeToSchema($parameter);

            if ($meta?->description !== null && $meta->description !== '') {
                $property['description'] = $meta->description;
            }

            if ($meta?->enum !== null) {
                $property['enum'] = $meta->enum;
            }

            $properties[$parameter->getName()] = $property;

            if (!$parameter->isDefaultValueAvailable() && !$parameter->allowsNull()) {
                $required[] = $parameter->getName();
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function typeToSchema(ReflectionParameter $parameter): array
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType) {
            return ['type' => 'string'];
        }

        $name = $type->getName();

        if (enum_exists($name)) {
            return static::enumToSchema($name);
        }

        return match ($name) {
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            default => ['type' => 'string'],
        };
    }

    /**
     * @param  class-string $enum
     * @return array<string, mixed>
     */
    protected static function enumToSchema(string $enum): array
    {
        $reflection = new ReflectionEnum($enum);

        if (!$reflection->isBacked()) {
            throw new AIException(sprintf('Tool parameter enum "%s" must be a backed enum.', $enum));
        }

        $backing = (string) $reflection->getBackingType();

        return [
            'type' => $backing === 'int' ? 'integer' : 'string',
            'enum' => array_map(
                static fn (BackedEnum $case): string|int => $case->value,
                $enum::cases(),
            ),
        ];
    }

    /**
     * Ghép mảng tham số do model trả về vào đúng thứ tự tham số của method.
     *
     * @param  array<int, ReflectionParameter> $parameters
     * @param  array<string, mixed>            $arguments
     * @return array<int, mixed>
     */
    protected static function bindArguments(array $parameters, array $arguments): array
    {
        $bound = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $arguments)) {
                $bound[] = static::castArgument($parameter, $arguments[$name]);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $bound[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $bound[] = null;
                continue;
            }

            throw new AIException(sprintf('Missing required tool argument "%s".', $name));
        }

        return $bound;
    }

    protected static function castArgument(ReflectionParameter $parameter, mixed $value): mixed
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $value === null) {
            return $value;
        }

        $name = $type->getName();

        if (enum_exists($name)) {
            return $name::tryFrom($value) ?? $value;
        }

        return match ($name) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => is_scalar($value) ? (string) $value : json_encode($value),
            default => $value,
        };
    }
}
