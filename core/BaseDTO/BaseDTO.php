<?php

namespace Ryra\Core\BaseDTO;

use Ryra\Core\Database\Model\Model;
use Ryra\Core\Support\Arrayable;
use Ryra\Core\Collection\Arr;
use Ryra\Core\Support\Str;
use IteratorAggregate;
use JsonSerializable;
use ArrayIterator;
use Traversable;

class BaseDTO implements IteratorAggregate, Arrayable, JsonSerializable
{
    public function __construct(array $fields = [])
    {
        foreach ($fields as $key => $value) {
            if (\property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function only(string|array $keys): array
    {
        return Arr::only($this->toArray(), (array) $keys);
    }

    public function except(string|array $keys): array
    {
        return Arr::except($this->toArray(), (array) $keys);
    }

    public static function fromArray(array $fields): static
    {
        return new static($fields);
    }

    public static function fromJson(string $json): static
    {
        if (Str::isJson($json)) {
            $json = \json_decode($json, true);
            return self::fromArray($json);
        }

        throw new \InvalidArgumentException('JSON string is invalid');
    }

    public function all(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        $array = \get_object_vars($this);
        foreach ($array as $key => $value) {
            unset($array[$key]);
            $array[$key] = $value;
        }
        return $array;
    }

    public function toJson(): string
    {
        return \json_encode($this->toArray());
    }

    public function toModel(string $model): Model
    {
        if (\is_a($model, Model::class)) {
            return (new Model())->setAttributes($this->toArray());
        }

        throw new \InvalidArgumentException('Model class is invalid');
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}