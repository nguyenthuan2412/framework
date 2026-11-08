<?php

namespace Ryra\Core\Http;

use Ryra\Core\Http\Exception\BadRequestHttpException;

final class InputBag extends ParameterBag
{
    /**
     * Replaces the current input values by a new set.
     */
    public function replace(array $inputs = []): void
    {
        $this->parameters = [];
        $this->add($inputs);
    }

    /**
     * Adds input values.
     */
    public function add(array $inputs = []): void
    {
        foreach ($inputs as $input => $value) {
            $this->set($input, $value);
        }
    }

    /**
     * Sets an input by name.
     */
    public function set(string $key, mixed $value): void
    {
        if (null !== $value && !\is_scalar($value) && !\is_array($value) && !$value instanceof \Stringable) {
            throw new \InvalidArgumentException(sprintf('Expected a scalar, or an array as a 2nd argument to "%s()", "%s" given.', __METHOD__, get_debug_type($value)));
        }

        $this->parameters[$key] = $value;
    }

    /**
     * Returns the parameter value converted to an enum.
     */
    public function getEnum(string $key, string $class, ?\BackedEnum $default = null): ?\BackedEnum
    {
        try {
            return parent::getEnum($key, $class, $default);
        } catch (\UnexpectedValueException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e, $e->getCode());
        }
    }

    /**
     * Returns the parameter value converted to string.
     */
    public function getString(string $key, string $default = ''): string
    {
        // Shortcuts the parent method because the validation on scalar is already done in get().
        return (string) $this->get($key, $default);
    }

    public function filter(string $key, mixed $default = null, int $filter = \FILTER_DEFAULT, mixed $options = []): mixed
    {
        $value = $this->has($key) ? $this->all()[$key] : $default;

        // Always turn $options into an array - this allows filter_var option shortcuts.
        if (!\is_array($options) && $options) {
            $options = ['flags' => $options];
        }

        if (\is_array($value) && !(($options['flags'] ?? 0) & (\FILTER_REQUIRE_ARRAY | \FILTER_FORCE_ARRAY))) {
            throw new BadRequestHttpException(sprintf('Input value "%s" contains an array, but "FILTER_REQUIRE_ARRAY" or "FILTER_FORCE_ARRAY" flags were not set.', $key));
        }

        if ((\FILTER_CALLBACK & $filter) && !(($options['options'] ?? null) instanceof \Closure)) {
            throw new \InvalidArgumentException(sprintf('A Closure must be passed to "%s()" when FILTER_CALLBACK is used, "%s" given.', __METHOD__, get_debug_type($options['options'] ?? null)));
        }

        $options['flags'] ??= 0;
        $nullOnFailure = $options['flags'] & \FILTER_NULL_ON_FAILURE;
        $options['flags'] |= \FILTER_NULL_ON_FAILURE;

        $value = filter_var($value, $filter, $options);

        if (null !== $value || $nullOnFailure) {
            return $value;
        }

        throw new BadRequestHttpException(sprintf('Input value "%s" is invalid and flag "FILTER_NULL_ON_FAILURE" was not set.', $key));
    }
}