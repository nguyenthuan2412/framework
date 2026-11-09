<?php

namespace Ryra\Core\Grpc\Metadata;

use Ryra\Core\Grpc\Attributes\Enum as EnumAttribute;
use Ryra\Core\Grpc\Attributes\Field;
use Ryra\Core\Grpc\Attributes\Ignore;
use Ryra\Core\Grpc\Attributes\Message;
use Ryra\Core\Grpc\Attributes\Rpc;
use Ryra\Core\Grpc\Attributes\Service;
use Ryra\Core\Grpc\Exception\MetadataException;
use Ryra\Core\Grpc\Messages\EmptyMessage;
use Ryra\Core\Grpc\Protobuf\Type;
use Ryra\Core\Grpc\Server\ServerContext;
use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Đọc attribute + kiểu PHP của class để dựng metadata protobuf.
 * Kết quả được cache theo class trong suốt vòng đời worker.
 */
final class MetadataFactory
{
    private const MAX_FIELD_NUMBER = 536870911;

    private const STREAM_TYPES = ['iterable', 'array', \Generator::class, \Traversable::class, \Iterator::class, \IteratorAggregate::class];

    /** @var array<string, MessageMetadata> */
    private static array $messages = [];

    /** @var array<string, EnumMetadata> */
    private static array $enums = [];

    /** @var array<string, ServiceMetadata> */
    private static array $services = [];

    /**
     * Class do protoc sinh ra (kế thừa Google\Protobuf\Internal\Message), dùng trực tiếp không cần map.
     */
    public static function isNative(string $class): bool
    {
        return \is_subclass_of($class, \Google\Protobuf\Internal\Message::class);
    }

    public static function forMessage(string $class): MessageMetadata
    {
        return self::$messages[$class] ??= self::buildMessage($class);
    }

    public static function forEnum(string $class): EnumMetadata
    {
        return self::$enums[$class] ??= self::buildEnum($class);
    }

    public static function forService(string $class): ServiceMetadata
    {
        return self::$services[$class] ??= self::buildService($class);
    }

    public static function clear(): void
    {
        self::$messages = self::$enums = self::$services = [];
    }

    // ------------------------------------------------------------------
    // Message
    // ------------------------------------------------------------------

    private static function buildMessage(string $class): MessageMetadata
    {
        if (!\class_exists($class)) {
            throw new MetadataException("Message class [$class] does not exist");
        }

        $reflection = new ReflectionClass($class);
        if (self::isNative($class)) {
            throw new MetadataException("[$class] is a protoc generated class, it has no #[Message] metadata");
        }
        if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isEnum()) {
            throw new MetadataException("Message [$class] must be a concrete class");
        }

        $attribute = ($reflection->getAttributes(Message::class)[0] ?? null)?->newInstance();
        $constructorDefaults = self::constructorDefaults($reflection);

        $fields = [];
        $usedNumbers = [];
        $usedNames = [];
        $maxNumber = 0;

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || !empty($property->getAttributes(Ignore::class))) {
                continue;
            }

            $fieldAttribute = ($property->getAttributes(Field::class)[0] ?? null)?->newInstance();
            $number = $fieldAttribute?->number ?? $maxNumber + 1;
            $location = "$class::\${$property->getName()}";

            if ($number < 1 || $number > self::MAX_FIELD_NUMBER || ($number >= 19000 && $number <= 19999)) {
                throw new MetadataException("Invalid field number $number on $location");
            }
            if (isset($usedNumbers[$number])) {
                throw new MetadataException("Duplicate field number $number on $location (already used by \${$usedNumbers[$number]})");
            }

            $field = self::buildField($property, $fieldAttribute, $number, $class, $constructorDefaults);
            if (isset($usedNames[$field->protoName])) {
                throw new MetadataException("Duplicate field name [{$field->protoName}] on $location");
            }

            $usedNumbers[$number] = $property->getName();
            $usedNames[$field->protoName] = true;
            $maxNumber = \max($maxNumber, $number);
            $fields[$property->getName()] = $field;
        }

        $name = $attribute?->name ?? $reflection->getShortName();
        $package = $attribute?->package;

        return new MessageMetadata(
            class: $class,
            name: $name,
            package: $package,
            fields: $fields,
            comment: $attribute?->comment,
            wellKnown: $package === 'google.protobuf' ? 'google/protobuf/' . self::snake($name) . '.proto' : null,
        );
    }

    private static function buildField(
        ReflectionProperty $property,
        ?Field $attribute,
        int $number,
        string $owner,
        array $constructorDefaults,
    ): FieldMetadata {
        $location = "$owner::\${$property->getName()}";
        $phpType = $property->getType();
        if (!$phpType instanceof ReflectionNamedType) {
            throw new MetadataException("Property $location must have a single (optionally nullable) type");
        }

        $typeName = $phpType->getName();
        if ($typeName === 'self' || $typeName === 'static') {
            $typeName = $property->getDeclaringClass()->getName();
        }

        $explicit = $attribute?->type;
        if (\is_string($explicit)) {
            $explicit = Type::tryFrom($explicit) ?? $explicit;
        }

        $repeated = false;
        $keyType = null;

        if ($typeName === 'array' || $typeName === 'iterable') {
            if ($explicit === null) {
                throw new MetadataException(
                    "Array property $location needs #[Field(type: ...)] to know the element type"
                );
            }
            [$type, $class] = self::resolveType($explicit, $location);
            if ($attribute?->key !== null) {
                if (!$attribute->key->isValidMapKey()) {
                    throw new MetadataException("Type {$attribute->key->value} cannot be used as map key on $location");
                }
                $keyType = $attribute->key;
            } else {
                $repeated = true;
            }
        } else {
            [$type, $class] = $explicit !== null
                ? self::resolveType($explicit, $location)
                : self::resolveType($typeName, $location);

            if ($type->isScalar() && $typeName !== 'mixed' && $type->phpType() !== $typeName) {
                throw new MetadataException(
                    "Proto type {$type->value} is not compatible with PHP type $typeName on $location"
                );
            }
            if (!$type->isScalar() && $class !== null && $typeName !== $class && !\is_a($class, $typeName, true)) {
                throw new MetadataException("Field type $class is not compatible with PHP type $typeName on $location");
            }
        }

        $hasDefault = false;
        $default = null;
        if ($property->isPromoted() && \array_key_exists($property->getName(), $constructorDefaults)) {
            $hasDefault = true;
            $default = $constructorDefaults[$property->getName()];
        } elseif (!$property->isPromoted() && $property->hasDefaultValue() && $property->hasType()) {
            $hasDefault = true;
            $default = $property->getDefaultValue();
        }

        return new FieldMetadata(
            property: $property->getName(),
            protoName: $attribute?->name ?? self::snake($property->getName()),
            number: $number,
            type: $type,
            class: $class,
            repeated: $repeated,
            keyType: $keyType,
            nullable: $phpType->allowsNull(),
            hasDefault: $hasDefault,
            default: $default,
            declaringClass: $property->getDeclaringClass()->getName(),
            deprecated: $attribute?->deprecated ?? false,
            comment: $attribute?->comment,
        );
    }

    /**
     * @return array{0: Type, 1: string|null}
     */
    private static function resolveType(Type|string $type, string $location): array
    {
        if ($type instanceof Type) {
            if (!$type->isScalar()) {
                throw new MetadataException("Use a class name instead of Type::{$type->name} on $location");
            }
            return [$type, null];
        }

        return match ($type) {
            'int' => [Type::Int64, null],
            'float' => [Type::Double, null],
            'bool' => [Type::Bool, null],
            'string' => [Type::String, null],
            default => self::resolveClassType($type, $location),
        };
    }

    /**
     * @return array{0: Type, 1: string}
     */
    private static function resolveClassType(string $class, string $location): array
    {
        if (\enum_exists($class)) {
            self::forEnum($class);
            return [Type::Enum, $class];
        }
        if ($class === \DateTimeInterface::class || \is_a($class, \DateTimeInterface::class, true)) {
            return [Type::Timestamp, $class];
        }
        if (self::isNative($class)) {
            throw new MetadataException("Protoc generated class [$class] cannot be used inside a #[Message] class on $location");
        }
        if (\class_exists($class)) {
            return [Type::Message, $class];
        }

        throw new MetadataException("Cannot map type [$class] to protobuf on $location");
    }

    private static function constructorDefaults(ReflectionClass $reflection): array
    {
        $defaults = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isPromoted() && $parameter->isDefaultValueAvailable()) {
                $defaults[$parameter->getName()] = $parameter->getDefaultValue();
            }
        }
        return $defaults;
    }

    // ------------------------------------------------------------------
    // Enum
    // ------------------------------------------------------------------

    private static function buildEnum(string $class): EnumMetadata
    {
        $reflection = new ReflectionEnum($class);
        if ((string) $reflection->getBackingType() !== 'int') {
            throw new MetadataException("Enum [$class] must be an int-backed enum to be used in protobuf");
        }

        $attribute = ($reflection->getAttributes(EnumAttribute::class)[0] ?? null)?->newInstance();
        $name = $attribute?->name ?? $reflection->getShortName();
        $prefix = ($attribute?->prefix ?? true) ? \strtoupper(self::snake($name)) . '_' : '';

        $values = [];
        foreach ($class::cases() as $case) {
            $values[$prefix . \strtoupper(self::snake($case->name))] = $case->value;
        }
        if (!\in_array(0, $values, true)) {
            // proto3 bắt buộc value đầu tiên bằng 0.
            $values = [$prefix . 'UNSPECIFIED' => 0] + $values;
        }
        \asort($values);

        return new EnumMetadata($class, $name, $attribute?->package, $values, $attribute?->comment);
    }

    // ------------------------------------------------------------------
    // Service
    // ------------------------------------------------------------------

    private static function buildService(string $class): ServiceMetadata
    {
        if (!\class_exists($class) && !\interface_exists($class)) {
            throw new MetadataException("Service [$class] does not exist");
        }

        $source = new ReflectionClass($class);
        $attribute = ($source->getAttributes(Service::class)[0] ?? null)?->newInstance();

        if ($attribute === null) {
            foreach ($source->getInterfaces() as $interface) {
                $attribute = ($interface->getAttributes(Service::class)[0] ?? null)?->newInstance();
                if ($attribute !== null) {
                    $source = $interface;
                    break;
                }
            }
        }

        if ($attribute === null) {
            throw new MetadataException("[$class] is not a gRPC service, add #[Service] to the class or its interface");
        }

        $methods = [];
        foreach ($source->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->isStatic()
                || $method->isConstructor()
                || \str_starts_with($method->getName(), '__')
                || $method->getDeclaringClass()->getName() !== $source->getName()
                || !empty($method->getAttributes(Ignore::class))
            ) {
                continue;
            }
            $metadata = self::buildMethod($method);
            if (isset($methods[$metadata->name])) {
                throw new MetadataException("Duplicate rpc name [{$metadata->name}] in service [$class]");
            }
            $methods[$metadata->name] = $metadata;
        }

        return new ServiceMetadata(
            class: $source->getName(),
            name: $attribute->name ?? $source->getShortName(),
            package: $attribute->package,
            methods: \array_values($methods),
            comment: $attribute->comment,
        );
    }

    private static function buildMethod(ReflectionMethod $method): MethodMetadata
    {
        $location = $method->getDeclaringClass()->getName() . '::' . $method->getName() . '()';
        $rpc = ($method->getAttributes(Rpc::class)[0] ?? null)?->newInstance();

        $requestPosition = null;
        $contextPosition = null;
        $requestClass = null;
        $clientStreaming = false;

        foreach ($method->getParameters() as $index => $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName === ServerContext::class) {
                $contextPosition = $index;
                continue;
            }
            if ($requestPosition !== null) {
                if (!$parameter->isOptional()) {
                    throw new MetadataException("Rpc $location only accepts a request message and an optional ServerContext");
                }
                continue;
            }

            $requestPosition = $index;
            if ($typeName === null || \in_array($typeName, self::STREAM_TYPES, true)) {
                $clientStreaming = $typeName !== null;
                $requestClass = $rpc?->request ?? throw new MetadataException(
                    "Rpc $location needs #[Rpc(request: Message::class)] because the request type cannot be inferred"
                );
            } else {
                $requestClass = $typeName;
            }
        }

        $returnType = $method->getReturnType();
        $returnName = $returnType instanceof ReflectionNamedType ? $returnType->getName() : null;
        $returnsVoid = \in_array($returnName, ['void', 'null', 'never'], true);
        $serverStreaming = $returnName !== null && \in_array($returnName, self::STREAM_TYPES, true);

        if ($returnsVoid) {
            $responseClass = EmptyMessage::class;
        } elseif ($serverStreaming || $returnName === null || $returnName === 'mixed' || $returnName === 'object') {
            $responseClass = $rpc?->response ?? throw new MetadataException(
                "Rpc $location needs #[Rpc(response: Message::class)] because the response type cannot be inferred"
            );
        } else {
            $responseClass = $rpc?->response ?? $returnName;
        }

        $requestClass ??= $rpc?->request ?? EmptyMessage::class;

        // Validate sớm để lỗi khai báo hiện ra ngay khi đăng ký service.
        foreach ([$requestClass, $responseClass] as $messageClass) {
            if (!self::isNative($messageClass)) {
                self::forMessage($messageClass);
            }
        }

        return new MethodMetadata(
            phpMethod: $method->getName(),
            name: $rpc?->name ?? \ucfirst($method->getName()),
            requestClass: $requestClass,
            responseClass: $responseClass,
            clientStreaming: $clientStreaming,
            serverStreaming: $serverStreaming,
            requestPosition: $requestPosition,
            contextPosition: $contextPosition,
            parameterCount: $method->getNumberOfParameters(),
            returnsVoid: $returnsVoid,
            comment: $rpc?->comment,
        );
    }

    public static function snake(string $value): string
    {
        $value = \preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value);
        $value = \preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $value);
        return \strtolower($value);
    }
}
