<?php

namespace Ryra\Core\Grpc\Protobuf;

use Ryra\Core\Grpc\Exception\DecodeException;
use Ryra\Core\Grpc\Metadata\FieldMetadata;
use Ryra\Core\Grpc\Metadata\MetadataFactory;

/**
 * Encode/decode message #[Message] (DTO) <-> wire format protobuf.
 *
 * Không phụ thuộc google/protobuf hay protoc: với mỗi class DTO, {@see WireCompiler} sinh sẵn một
 * encoder/decoder chuyên biệt (đọc/ghi thẳng property <-> bytes), cache trong suốt vòng đời worker.
 */
final class Codec
{
    private const MAX_DEPTH = 100;

    /** @var array<string, \Closure> */
    private static array $hydrators = [];

    /** @var array<string, \ReflectionClass> */
    private static array $reflections = [];

    private static ?\DateTimeZone $timezone = null;

    /** @var array<string, \Closure(object): string> */
    private array $wireEncoders = [];

    /** @var array<string, \Closure(string): object> */
    private array $wireDecoders = [];

    private ?WireCompiler $wireCompiler = null;

    /** Độ sâu decode hiện tại của message đệ quy (decode chạy đồng bộ, không yield giữa chừng). */
    private int $depth = 0;

    /**
     * @param string|null $cacheDir Thư mục ghi code encoder/decoder sinh ra (để OPcache/JIT tối ưu được).
     *                              null: chạy code qua eval(), không ghi file (chậm hơn ~25%).
     */
    public function __construct(private readonly ?string $cacheDir = null) {}

    public function encode(object $message): string
    {
        return ($this->wireEncoders[$message::class] ??= $this->wire()->encoder($message::class))($message);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function decode(string $bytes, string $class): object
    {
        return ($this->wireDecoders[$class] ??= $this->wire()->decoder($class))($bytes);
    }

    // ------------------------------------------------------------------
    // Dùng bởi code do WireCompiler sinh ra
    // ------------------------------------------------------------------

    /**
     * @internal
     */
    public function cacheDir(): ?string
    {
        return $this->cacheDir;
    }

    private function wire(): WireCompiler
    {
        return $this->wireCompiler ??= new WireCompiler($this);
    }

    /**
     * @internal
     */
    public function wireEncoder(string $class): \Closure
    {
        return $this->wireEncoders[$class] ??= $this->wire()->encoder($class);
    }

    /**
     * @internal
     */
    public function wireDecoder(string $class): \Closure
    {
        return $this->wireDecoders[$class] ??= $this->wire()->decoder($class);
    }

    /**
     * Bảng encoder theo class (tham chiếu), để code sinh ra gọi thẳng encoder của message con.
     *
     * @internal
     * @return array<string, \Closure(object): string>
     */
    public function &wireEncoders(): array
    {
        return $this->wireEncoders;
    }

    /**
     * @internal
     * @return array<string, \Closure(string): object>
     */
    public function &wireDecoders(): array
    {
        return $this->wireDecoders;
    }

    /**
     * Decode message con thuộc type đệ quy, có giới hạn độ sâu.
     *
     * @internal
     */
    public function decodeNested(string $bytes, string $class): object
    {
        if ($this->depth >= self::MAX_DEPTH) {
            throw new DecodeException('Message nesting exceeds ' . self::MAX_DEPTH . ' levels');
        }
        $this->depth++;
        try {
            return ($this->wireDecoders[$class] ?? $this->wireDecoder($class))($bytes);
        } finally {
            $this->depth--;
        }
    }

    /**
     * @internal
     */
    public function decodeTimestamp(string $bytes, string $class): \DateTimeInterface
    {
        [$seconds, $nanos] = Wire::readTimestamp($bytes);
        if ($class === \DateTimeInterface::class) {
            $class = \DateTimeImmutable::class;
        }
        if (\PHP_VERSION_ID >= 80400) {
            // Nhanh hơn ~10 lần so với parse chuỗi.
            $date = $class::createFromTimestamp($seconds)->setMicrosecond(\intdiv($nanos, 1000));
        } else {
            $date = $class::createFromFormat('U.u', \sprintf('%d.%06d', $seconds, \intdiv($nanos, 1000)));
        }

        $timezone = \date_default_timezone_get();
        if (self::$timezone?->getName() !== $timezone) {
            self::$timezone = new \DateTimeZone($timezone);
        }
        return $date->setTimezone(self::$timezone);
    }

    /**
     * @internal
     */
    public function ensureObject(FieldMetadata $field, mixed $value): object
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

    /**
     * @internal
     */
    public function toEnum(FieldMetadata $field, int $value): ?\BackedEnum
    {
        /** @var class-string<\BackedEnum> $enum */
        $enum = $field->class;
        return $enum::tryFrom($value)
            ?? ($field->hasDefault ? $field->default : ($field->nullable ? null : $enum::cases()[0]));
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
            default => self::cast($field->type, null),
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
            default => self::cast($field->type, $value),
        };
    }

    private static function cast(Type $type, mixed $value): mixed
    {
        return match ($type->phpType()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            default => (string) $value,
        };
    }

    /**
     * Chuyển DTO thành array đệ quy (key là tên field proto), tiện để log / trả JSON.
     */
    public function toArray(object $message): array
    {
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
        $object = (self::$reflections[$class] ??= new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $fields = MetadataFactory::forMessage($class)->fields;

        $byScope = [];
        foreach ($values as $name => $value) {
            $byScope[$fields[$name]->declaringClass][$name] = $value;
        }

        foreach ($byScope as $scope => $scopeValues) {
            self::hydrator($scope)($object, $scopeValues);
        }

        return $object;
    }

    /**
     * @internal
     */
    public static function hydrator(string $scope): \Closure
    {
        return self::$hydrators[$scope] ??= \Closure::bind(static function (object $object, array $values): void {
            foreach ($values as $name => $value) {
                $object->$name = $value;
            }
        }, null, $scope);
    }
}
