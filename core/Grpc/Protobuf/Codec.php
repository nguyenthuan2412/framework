<?php

namespace Ryra\Core\Grpc\Protobuf;

use Google\Protobuf\GPBEmpty;
use Google\Protobuf\Internal\Message as NativeMessage;
use Google\Protobuf\Timestamp;
use Ryra\Core\Grpc\Exception\DecodeException;
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
 *
 * Việc map DTO <-> class protoc được "biên dịch" một lần cho mỗi class thành closure chuyên biệt
 * (đọc/ghi property, gọi getter theo tên cố định, không match theo kiểu field ở mỗi lần gọi),
 * nên chi phí còn lại gần như chỉ là của google/protobuf.
 */
final class Codec
{
    /** @var array<string, \Closure> */
    private static array $hydrators = [];

    /** @var array<string, string> */
    private static array $methodNames = [];

    /** @var array<string, \ReflectionClass> */
    private static array $reflections = [];

    private static ?\DateTimeZone $timezone = null;

    /** @var array<string, string> DTO hoặc class protoc => class protoc */
    private array $nativeClasses = [];

    /** @var array<string, \Closure(object): NativeMessage> */
    private array $encoders = [];

    /** @var array<string, \Closure(NativeMessage): object> */
    private array $decoders = [];

    /** @var array<string, \Closure(object): string> */
    private array $wireEncoders = [];

    /** @var array<string, \Closure(string): object> */
    private array $wireDecoders = [];

    private ?WireCompiler $wireCompiler = null;

    private const MAX_DEPTH = 100;

    /** Độ sâu decode hiện tại của message đệ quy (mỗi coroutine decode đồng bộ, không yield giữa chừng). */
    private int $depth = 0;

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
        $this->nativeClasses = $this->encoders = $this->decoders = [];
    }

    public function nativeClass(string $class): string
    {
        if (isset($this->nativeClasses[$class])) {
            return $this->nativeClasses[$class];
        }
        if (MetadataFactory::isNative($class)) {
            return $this->nativeClasses[$class] = $class;
        }
        return $this->nativeClasses[$class] = $this->classMap[$class] ?? throw new MetadataException(
            "Message [$class] has not been compiled yet. Run `php ryra grpc:generate`."
        );
    }

    public function encode(object $message): string
    {
        if ($message instanceof NativeMessage) {
            return $message->serializeToString();
        }
        return ($this->wireEncoders[$message::class] ??= $this->wire()->encoder($message::class))($message);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function decode(string $bytes, string $class): object
    {
        if (isset($this->wireDecoders[$class])) {
            return $this->wireDecoders[$class]($bytes);
        }
        if (MetadataFactory::isNative($class)) {
            $native = new $class();
            $native->mergeFromString($bytes);
            return $native;
        }
        return ($this->wireDecoders[$class] = $this->wire()->decoder($class))($bytes);
    }

    // ------------------------------------------------------------------
    // Wire format trực tiếp (DTO <-> bytes), dùng bởi code do WireCompiler sinh ra
    // ------------------------------------------------------------------

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
     * Decode message con thuộc type đệ quy, có giới hạn độ sâu (giống google/protobuf).
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
     * @internal
     */
    public function decodeTimestamp(string $bytes, string $class): \DateTimeInterface
    {
        [$seconds, $nanos] = Wire::readTimestamp($bytes);
        return $this->dateTime($seconds, $nanos, $class);
    }

    // ------------------------------------------------------------------
    // DTO -> native
    // ------------------------------------------------------------------

    public function toNative(object $message): NativeMessage
    {
        if ($message instanceof NativeMessage) {
            return $message;
        }
        return ($this->encoders[$message::class] ??= $this->compileEncoder($message::class))($message);
    }

    /**
     * Sinh closure chuyển DTO -> class protoc cho riêng $class, vd với HelloReply:
     *
     *   static function (object $m) use ($c, $F): NativeMessage {
     *       $d = [];
     *       if (($v = $m->message ?? null) !== null) { $d['message'] = (string) $v; }
     *       if (($v = $m->repliedAt ?? null) !== null) { $d['replied_at'] = $v instanceof \DateTimeInterface ? ... }
     *       return new \GrpcStub\Example\V1\HelloReply($d);
     *   }
     */
    private function compileEncoder(string $class): \Closure
    {
        $nativeClass = '\\' . \ltrim($this->nativeClass($class), '\\');
        $F = MetadataFactory::forMessage($class)->fields;
        $c = $this;

        $code = "static function (object \$m) use (\$c, \$F): \\" . NativeMessage::class . " {\n    \$d = [];\n";
        foreach ($F as $name => $field) {
            $key = \var_export($field->protoName, true);
            $ref = \var_export($name, true);
            $code .= "    if ((\$v = \$m->{$name} ?? null) !== null) {\n";
            if ($field->isMap()) {
                $keyExpr = match ($field->keyType) {
                    Type::String => '(string) $k',
                    Type::Bool => '(bool) $k',
                    default => '(int) $k',
                };
                $code .= "        \$i = [];\n        foreach (\$v as \$k => \$x) { \$i[$keyExpr] = " . $this->encodeExpr($field, '$x', $ref) . "; }\n"
                    . "        \$d[$key] = \$i;\n";
            } elseif ($field->repeated) {
                $code .= "        \$i = [];\n        foreach (\$v as \$x) { \$i[] = " . $this->encodeExpr($field, '$x', $ref) . "; }\n"
                    . "        \$d[$key] = \$i;\n";
            } else {
                $code .= "        \$d[$key] = " . $this->encodeExpr($field, '$v', $ref) . ";\n";
            }
            $code .= "    }\n";
        }
        $code .= "    return new $nativeClass(\$d);\n}";

        return eval("return $code;");
    }

    private function encodeExpr(FieldMetadata $field, string $var, string $ref): string
    {
        $fqcn = $field->class !== null ? '\\' . \ltrim($field->class, '\\') : null;
        return match ($field->type) {
            Type::Message => "\$c->toNative($var instanceof $fqcn ? $var : \$c->ensureObject(\$F[$ref], $var))",
            Type::Enum => "($var instanceof \\BackedEnum ? {$var}->value : (int) $var)",
            Type::Timestamp => "\$c->toTimestamp($var)",
            default => match ($field->type->phpType()) {
                'int' => "(int) $var",
                'float' => "(float) $var",
                'bool' => "(bool) $var",
                default => "(string) $var",
            },
        };
    }

    /**
     * @internal Dùng bởi encoder được sinh ra.
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
     * @internal Dùng bởi encoder được sinh ra.
     */
    public function toTimestamp(\DateTimeInterface|int|string $value): Timestamp
    {
        if (!$value instanceof \DateTimeInterface) {
            $value = new \DateTimeImmutable(\is_int($value) ? "@$value" : $value);
        }
        $timestamp = new Timestamp();
        $timestamp->setSeconds($value->getTimestamp());
        $timestamp->setNanos((int) $value->format('u') * 1000);
        return $timestamp;
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
        return ($this->decoders[$class] ??= $this->compileDecoder($class))($native);
    }

    /**
     * Sinh closure chuyển class protoc -> DTO cho riêng $class. Closure được bind vào scope của DTO
     * nên gán thẳng được cả property readonly, không cần gọi constructor. Vd với HelloReply:
     *
     *   static function (\Google\Protobuf\Internal\Message $n) use ($c, $F, $D, $R, $H): object {
     *       $o = $R->newInstanceWithoutConstructor();
     *       $o->message = $n->getMessage();
     *       $o->repliedAt = ($v = $n->getRepliedAt()) === null ? null : $c->fromTimestamp($v, '...');
     *       return $o;
     *   }
     */
    private function compileDecoder(string $class): \Closure
    {
        $F = MetadataFactory::forMessage($class)->fields;
        $D = [];
        $H = [];
        $R = self::$reflections[$class] ??= new \ReflectionClass($class);
        $c = $this;

        $code = "static function (\\" . NativeMessage::class . " \$n) use (\$c, \$F, \$D, \$R, \$H): object {\n"
            . "    \$o = \$R->newInstanceWithoutConstructor();\n";
        foreach ($F as $name => $field) {
            $ref = \var_export($name, true);
            $camel = self::camel($field->protoName);
            $get = "\$n->get$camel()";

            if ($field->isMap() || $field->repeated) {
                if ($field->type === Type::Message || $field->type === Type::Enum || $field->type === Type::Timestamp) {
                    $target = $field->isMap() ? '$i[$k]' : '$i[]';
                    $code .= "    \$i = [];\n    foreach ($get as \$k => \$x) { $target = " . $this->decodeExpr($field, '$x', $ref) . "; }\n";
                    $expr = '$i';
                } else {
                    $expr = "\\iterator_to_array($get, " . ($field->isMap() ? 'true' : 'false') . ')';
                }
            } elseif ($field->isOptional()) {
                if ($field->hasDefault) {
                    $D[$name] = $field->default;
                }
                $fallback = $field->hasDefault ? "\$D[$ref]" : 'null';
                $expr = "(\$n->has$camel() ? " . $this->decodeExpr($field, $get, $ref) . " : $fallback)";
            } elseif ($field->type === Type::Message || $field->type === Type::Timestamp) {
                // Message con không được gửi lên.
                $empty = $field->nullable ? 'null' : "\$c->emptyValue(\$F[$ref])";
                $expr = "((\$v = $get) === null ? $empty : " . $this->decodeExpr($field, '$v', $ref) . ")";
            } else {
                $expr = $this->decodeExpr($field, $get, $ref);
            }

            if ($field->declaringClass === $class) {
                $code .= "    \$o->{$name} = $expr;\n";
            } else {
                // Property khai báo ở class cha: gán qua hydrator đúng scope (cần cho readonly).
                $H[$field->declaringClass] ??= self::hydrator($field->declaringClass);
                $scope = \var_export($field->declaringClass, true);
                $code .= "    \$H[$scope](\$o, [$ref => $expr]);\n";
            }
        }
        $code .= "    return \$o;\n}";

        return \Closure::bind(eval("return $code;"), null, $class);
    }

    private function decodeExpr(FieldMetadata $field, string $var, string $ref): string
    {
        $class = $field->class !== null ? \var_export($field->class, true) : null;
        return match ($field->type) {
            Type::Message => "\$c->fromNative($var, $class)",
            Type::Enum => "\$c->toEnum(\$F[$ref], (int) $var)",
            Type::Timestamp => "\$c->fromTimestamp($var, $class)",
            default => $var,
        };
    }

    /**
     * @internal Dùng bởi decoder được sinh ra.
     */
    public function emptyValue(FieldMetadata $field): mixed
    {
        return match ($field->type) {
            Type::Message => $this->fromNative(new ($this->nativeClass($field->class))(), $field->class),
            Type::Timestamp => $this->fromTimestamp(new Timestamp(), $field->class),
            default => null,
        };
    }

    /**
     * @internal Dùng bởi decoder được sinh ra.
     */
    public function toEnum(FieldMetadata $field, int $value): ?\BackedEnum
    {
        /** @var class-string<\BackedEnum> $enum */
        $enum = $field->class;
        return $enum::tryFrom($value)
            ?? ($field->hasDefault ? $field->default : ($field->nullable ? null : $enum::cases()[0]));
    }

    /**
     * @internal Dùng bởi decoder được sinh ra.
     */
    public function fromTimestamp(Timestamp $timestamp, string $class): \DateTimeInterface
    {
        return $this->dateTime($timestamp->getSeconds(), $timestamp->getNanos(), $class);
    }

    private function dateTime(int $seconds, int $nanos, string $class): \DateTimeInterface
    {
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
