<?php

namespace Ryra\Core\Grpc\Protobuf;

use Google\Protobuf\GPBEmpty;
use Google\Protobuf\Internal\Message as NativeMessage;
use Google\Protobuf\Timestamp;
use Ryra\Core\Grpc\Exception\MetadataException;
use Ryra\Core\Grpc\Messages\EmptyMessage;
use Ryra\Core\Grpc\Metadata\FieldMetadata;
use Ryra\Core\Grpc\Metadata\MetadataFactory;

/**
 * Encode/decode message bằng google/protobuf.
 *
 * - Class do protoc sinh ra: dùng trực tiếp serializeToString / mergeFromString.
 * - Class #[Message] (DTO): map sang class protoc tương ứng (được sinh bởi `grpc:generate`)
 *   rồi để google/protobuf serialize.
 */
final class Codec
{
    /** @var array<string, \Closure> */
    private static array $hydrators = [];

    /** @var array<string, string> */
    private static array $methodNames = [];

    /**
     * @param array<string, string> $classMap DTO class => class do protoc sinh ra.
     */
    public function __construct(private array $classMap = [])
    {
        $this->classMap[EmptyMessage::class] = GPBEmpty::class;
    }

    public function addClassMap(array $classMap): void
    {
        $this->classMap = $classMap + $this->classMap;
    }

    public function nativeClass(string $class): string
    {
        if (MetadataFactory::isNative($class)) {
            return $class;
        }
        return $this->classMap[$class] ?? throw new MetadataException(
            "Message [$class] has not been compiled yet. Run `php ryra grpc:generate`."
        );
    }

    public function encode(object $message): string
    {
        return $this->toNative($message)->serializeToString();
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function decode(string $bytes, string $class): object
    {
        $native = new ($this->nativeClass($class))();
        $native->mergeFromString($bytes);
        return MetadataFactory::isNative($class) ? $native : $this->fromNative($native, $class);
    }

    // ------------------------------------------------------------------
    // DTO -> native
    // ------------------------------------------------------------------

    public function toNative(object $message): NativeMessage
    {
        if ($message instanceof NativeMessage) {
            return $message;
        }

        $nativeClass = $this->nativeClass($message::class);
        $data = [];
        foreach (MetadataFactory::forMessage($message::class)->fields as $name => $field) {
            $value = $message->$name ?? null;
            if ($value === null) {
                continue;
            }
            if ($field->repeated || $field->isMap()) {
                $items = [];
                foreach ($value as $key => $item) {
                    $items[$field->isMap() ? $this->castKey($field, $key) : \count($items)] = $this->toNativeValue($field, $item);
                }
                $data[$field->protoName] = $items;
            } else {
                $data[$field->protoName] = $this->toNativeValue($field, $value);
            }
        }

        return new $nativeClass($data);
    }

    private function toNativeValue(FieldMetadata $field, mixed $value): mixed
    {
        return match ($field->type) {
            Type::Message => $this->toNative($this->ensureObject($field, $value)),
            Type::Enum => $value instanceof \BackedEnum ? $value->value : (int) $value,
            Type::Timestamp => $this->toTimestamp($value),
            default => $this->cast($field->type, $value),
        };
    }

    private function ensureObject(FieldMetadata $field, mixed $value): object
    {
        if ($value instanceof $field->class) {
            return $value;
        }
        if (\is_array($value)) {
            return $this->fromArray($value, $field->class);
        }
        throw new \InvalidArgumentException(
            "Field {$field->declaringClass}::\${$field->property} expects {$field->class}, got " . \get_debug_type($value)
        );
    }

    private function toTimestamp(\DateTimeInterface|int|string $value): Timestamp
    {
        if (!$value instanceof \DateTimeInterface) {
            $value = new \DateTimeImmutable(\is_int($value) ? "@$value" : $value);
        }
        return new Timestamp([
            'seconds' => $value->getTimestamp(),
            'nanos' => (int) $value->format('u') * 1000,
        ]);
    }

    private function castKey(FieldMetadata $field, int|string $key): int|string|bool
    {
        return match ($field->keyType) {
            Type::String => (string) $key,
            Type::Bool => (bool) $key,
            default => (int) $key,
        };
    }

    private function cast(Type $type, mixed $value): mixed
    {
        return match ($type->phpType()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            default => (string) $value,
        };
    }

    // ------------------------------------------------------------------
    // native -> DTO
    // ------------------------------------------------------------------

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function fromNative(NativeMessage $native, string $class): object
    {
        $values = [];
        foreach (MetadataFactory::forMessage($class)->fields as $name => $field) {
            $camel = self::camel($field->protoName);

            if ($field->isOptional() && !$native->{'has' . $camel}()) {
                $values[$name] = $field->hasDefault ? $field->default : null;
                continue;
            }

            $value = $native->{'get' . $camel}();

            if ($field->isMap()) {
                $items = [];
                foreach ($value as $key => $item) {
                    $items[$key] = $this->fromNativeValue($field, $item);
                }
                $values[$name] = $items;
            } elseif ($field->repeated) {
                $items = [];
                foreach ($value as $item) {
                    $items[] = $this->fromNativeValue($field, $item);
                }
                $values[$name] = $items;
            } elseif ($value === null) {
                // Message con không được gửi lên.
                $values[$name] = $field->nullable ? null : $this->emptyValue($field);
            } else {
                $values[$name] = $this->fromNativeValue($field, $value);
            }
        }

        return self::hydrate($class, $values);
    }

    private function fromNativeValue(FieldMetadata $field, mixed $value): mixed
    {
        return match ($field->type) {
            Type::Message => $this->fromNative($value, $field->class),
            Type::Enum => $this->toEnum($field, (int) $value),
            Type::Timestamp => $this->fromTimestamp($value, $field->class),
            default => $value,
        };
    }

    private function emptyValue(FieldMetadata $field): mixed
    {
        return match ($field->type) {
            Type::Message => $this->fromNative(new ($this->nativeClass($field->class))(), $field->class),
            Type::Timestamp => $this->fromTimestamp(new Timestamp(), $field->class),
            default => null,
        };
    }

    private function toEnum(FieldMetadata $field, int $value): ?\BackedEnum
    {
        /** @var class-string<\BackedEnum> $enum */
        $enum = $field->class;
        return $enum::tryFrom($value)
            ?? ($field->hasDefault ? $field->default : ($field->nullable ? null : $enum::cases()[0]));
    }

    private function fromTimestamp(Timestamp $timestamp, string $class): \DateTimeInterface
    {
        if ($class === \DateTimeInterface::class) {
            $class = \DateTimeImmutable::class;
        }
        $date = $class::createFromFormat(
            'U.u',
            \sprintf('%d.%06d', $timestamp->getSeconds(), \intdiv($timestamp->getNanos(), 1000)),
        );
        return $date->setTimezone(new \DateTimeZone(\date_default_timezone_get()));
    }

    /**
     * Tên getter/setter mà protoc sinh ra cho field (giống UnderscoresToCamelCase của protoc).
     */
    public static function camel(string $name): string
    {
        if (isset(self::$methodNames[$name])) {
            return self::$methodNames[$name];
        }

        $result = '';
        $capNext = true;
        foreach (\str_split($name) as $char) {
            if (\ctype_lower($char)) {
                $result .= $capNext ? \strtoupper($char) : $char;
                $capNext = false;
            } elseif (\ctype_upper($char)) {
                $result .= $char;
                $capNext = false;
            } elseif (\ctype_digit($char)) {
                $result .= $char;
                $capNext = true;
            } else {
                $capNext = true;
            }
        }

        return self::$methodNames[$name] = $result;
    }

    // ------------------------------------------------------------------
    // Array helpers
    // ------------------------------------------------------------------

    /**
     * Tạo DTO từ array (key là tên property hoặc tên field proto), message con có thể là array lồng nhau.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function fromArray(array $data, string $class): object
    {
        $values = [];
        foreach (MetadataFactory::forMessage($class)->fields as $name => $field) {
            if (\array_key_exists($name, $data)) {
                $value = $data[$name];
            } elseif (\array_key_exists($field->protoName, $data)) {
                $value = $data[$field->protoName];
            } else {
                $values[$name] = $this->defaultValue($field);
                continue;
            }

            if ($value === null) {
                $values[$name] = null;
            } elseif ($field->repeated || $field->isMap()) {
                $items = [];
                foreach ($value as $key => $item) {
                    $items[$key] = $this->arrayItem($field, $item);
                }
                $values[$name] = $field->repeated ? \array_values($items) : $items;
            } else {
                $values[$name] = $this->arrayItem($field, $value);
            }
        }

        return self::hydrate($class, $values);
    }

    private function defaultValue(FieldMetadata $field): mixed
    {
        return match (true) {
            $field->hasDefault => $field->default,
            $field->repeated || $field->isMap() => [],
            $field->nullable => null,
            $field->type === Type::Message => $this->fromArray([], $field->class),
            $field->type === Type::Enum => $this->toEnum($field, 0),
            $field->type === Type::Timestamp => new \DateTimeImmutable('@0'),
            default => $this->cast($field->type, null),
        };
    }

    private function arrayItem(FieldMetadata $field, mixed $value): mixed
    {
        return match ($field->type) {
            Type::Message => \is_array($value) ? $this->fromArray($value, $field->class) : $value,
            Type::Enum => $value instanceof \BackedEnum ? $value : $field->class::from((int) $value),
            Type::Timestamp => $value instanceof \DateTimeInterface
                ? $value
                : new \DateTimeImmutable(\is_int($value) ? "@$value" : $value),
            default => $this->cast($field->type, $value),
        };
    }

    /**
     * Chuyển DTO thành array đệ quy (key là tên field proto), tiện để log / trả JSON.
     */
    public function toArray(object $message): array
    {
        if ($message instanceof NativeMessage) {
            return \json_decode($message->serializeToJsonString(), true) ?? [];
        }

        $out = [];
        foreach (MetadataFactory::forMessage($message::class)->fields as $name => $field) {
            $out[$field->protoName] = $this->exportValue($message->$name ?? null);
        }
        return $out;
    }

    private function exportValue(mixed $value): mixed
    {
        return match (true) {
            \is_array($value) => \array_map($this->exportValue(...), $value),
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::RFC3339_EXTENDED),
            \is_object($value) => $this->toArray($value),
            default => $value,
        };
    }

    /**
     * Gán giá trị cho DTO mà không gọi constructor (hỗ trợ cả property readonly).
     */
    private static function hydrate(string $class, array $values): object
    {
        $object = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $fields = MetadataFactory::forMessage($class)->fields;

        $byScope = [];
        foreach ($values as $name => $value) {
            $byScope[$fields[$name]->declaringClass][$name] = $value;
        }

        foreach ($byScope as $scope => $scopeValues) {
            $hydrator = self::$hydrators[$scope] ??= \Closure::bind(static function (object $object, array $values): void {
                foreach ($values as $name => $value) {
                    $object->$name = $value;
                }
            }, null, $scope);
            $hydrator($object, $scopeValues);
        }

        return $object;
    }
}
