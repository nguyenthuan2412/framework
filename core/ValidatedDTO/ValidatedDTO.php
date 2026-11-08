<?php

namespace Ryra\Core\ValidatedDTO;

use Ryra\Core\ValidatedDTO\ValidationException;
use Ryra\Core\ValidatedDTO\Rules\IsList;
use Ryra\Core\ValidatedDTO\Rules\IsMap;
use Ryra\Core\BaseDTO\BaseDTO;
use Ryra\Core\Http\Response;
use Ryra\Core\Http\Request;
use Ryra\Core\Support\Str;
use ReflectionAttribute;

class ValidatedDTO extends BaseDTO
{
    protected static array $schemas = [];

    public function __construct(array $fields, bool $validate = false)
    {
        if (!$this->has(static::class)) {
            $this->compile(static::class);
        }

        $this->hydrate($fields);

        if ($validate) {
            $this->validate();
        }
    }

    public static function failedValidation(Request $request, ValidationException $th): Response
    {
        if ($request->wantsJson() || $request->hasSession()) {
            return Response::json([
                'message' => $th->getMessage(),
                'errors' => $th->getErrors()
            ])->setStatusCode($th->getStatusCode());
        }

        return Response::redirect()->back()
            ->withErrors($th->getErrors())
            ->withInput();
    }

    private function has(string $schema): bool
    {
        return isset(static::$schemas[$schema]);
    }

    private function hydrate(array $fields): void
    {
        /** @var Property[] */
        $schema = static::$schemas[static::class];
        foreach ($schema as $property) {
            $value = $fields[$property->name] ?? null;

            if (
                !empty($rule = $property->getRule(IsMap::class))
                && !empty($rule->dtoClass)
                && \is_array($value)
                && !($value instanceof $rule->dtoClass)
            ) {
                $childSchema = $rule->dtoClass;
                $value = new $childSchema($value);
            } else if (
                !empty($rule = $property->getRule(IsList::class))
                && \is_array($value)
                && !empty($rule->ruleOrSchema)
                && is_a($rule->ruleOrSchema, ValidatedDTO::class, true)
            ) {
                $childSchema = $rule->ruleOrSchema;
                $value = array_map(fn($item) => new $childSchema((array) $item), $value);
            }

            if ($value !== null) {
                $this->{$property->name} = $value;
            }
        }
    }

    private function compile(string $schema): void
    {
        $properties = [];
        $reflectionClass = new \ReflectionClass($schema);
        $reflectionProperties = $reflectionClass->getProperties();
        foreach ($reflectionProperties as $reflectionProperty) {
            if ($reflectionProperty->isStatic()) {
                continue;
            }

            $propertyName = $reflectionProperty->getName();

            $rules = $reflectionProperty->getAttributes(
                Rule::class,
                ReflectionAttribute::IS_INSTANCEOF
            );
            $property = new Property($propertyName);
            if (!empty($rules)) {
                foreach ($rules as $index => $rule) {
                    $instance = $rule->newInstance();
                    $instance->setSchema($schema);

                    $rules[$rule->getName()] = $instance;
                    unset($rules[$index]);
                }

                $property->setRules($rules);

                // compile nested DTOs khi gặp IsMap hoặc IsList với DTO schema
                $isMapRule = $property->getRule(IsMap::class);
                if ($isMapRule instanceof IsMap && !empty($isMapRule->dtoClass)) {
                    if (!isset(static::$schemas[$isMapRule->dtoClass])) {
                        $this->compile($isMapRule->dtoClass);
                    }
                }

                $isListRule = $property->getRule(IsList::class);
                if ($isListRule instanceof IsList && !empty($isListRule->ruleOrSchema)) {
                    $nested = $isListRule->ruleOrSchema;
                    if (
                        !isset(static::$schemas[$nested])
                        && \is_a($nested, ValidatedDTO::class, true)
                    ) {
                        $this->compile($nested);
                    }
                }
            }
            $properties[] = $property;
        }
        static::$schemas[$schema] = $properties;
    }

    private function validate(): void
    {
        $values = $this->toArray();
        $errors = $this->handleValidate(static::class, $values, $values);

        if (!empty($errors)) {
            throw new ValidationException('The given data was invalid.', static::class, $errors);
        }
    }

    private function handleValidate(string $schema, array $values = [], array &$dtoValues = []): array
    {
        $errors = [];
        $properties = static::$schemas[$schema];
        foreach ($properties as $property) {
            $value = $values[$property->name] ?? null;
            if ($property->hasNullable() && \is_null($value)) {
                continue;
            }

            $rules = $property->getRules();
            foreach ($rules as $ruleName => $rule) {
                if ($rule->check($value, $values, $property) === false) {
                    $errors[$property->name][] = $rule->message($value, $property->name);
                    if ($property->hasBail()) {
                        break;
                    }
                }

                if ($ruleName === IsList::class && !empty($rule->ruleOrSchema)) {
                    $isRule = \is_a($rule->ruleOrSchema, Rule::class, true);
                    foreach ((array) $value as $index => $item) {
                        if ($isRule) {
                            if ((new $rule->ruleOrSchema)->check($item, $dtoValues, $property) === false) {
                                $errors["{$property->name}.$index"][] = (new $rule->ruleOrSchema)->message($item, "{$property->name}.$index");
                            }
                            continue;
                        }

                        $errors = array_merge($errors, $this->handleValidate($rule->ruleOrSchema, (array) $item, $dtoValues));
                    }
                    continue;
                }

                if ($ruleName === IsMap::class && !empty($rule->dtoClass)) {
                    $nestedValues = $value instanceof ValidatedDTO ? $value->toArray() : (array) $value;
                    $errors = array_merge($errors, $this->handleValidate($rule->dtoClass, $nestedValues, $dtoValues));
                }
            }
        }

        return $errors;
    }

    public static function fromArray(array $fields, bool $validate = false): static
    {
        return new static($fields, $validate);
    }

    public static function fromJson(string $json, bool $validate = false): static
    {
        if (Str::isJson($json)) {
            $json = \json_decode($json, true);
            return self::fromArray($json, $validate);
        }

        throw new \InvalidArgumentException('JSON string is invalid');
    }
}