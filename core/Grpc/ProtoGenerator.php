<?php

namespace Ryra\Core\Grpc;

use Ryra\Core\Grpc\Exception\MetadataException;
use Ryra\Core\Grpc\Metadata\EnumMetadata;
use Ryra\Core\Grpc\Metadata\FieldMetadata;
use Ryra\Core\Grpc\Metadata\MessageMetadata;
use Ryra\Core\Grpc\Metadata\MetadataFactory;
use Ryra\Core\Grpc\Metadata\ServiceMetadata;
use Ryra\Core\Grpc\Protobuf\Type;

/**
 * Sinh nội dung file .proto từ các class #[Service] / #[Message].
 *
 * Mỗi package proto là một file `<package>.proto`. Message/enum không khai báo package
 * sẽ thuộc package của service đầu tiên sử dụng nó.
 */
final class ProtoGenerator
{
    /** @var array<string, string> class => package */
    private array $packages = [];

    /** @var array<string, array{services: ServiceMetadata[], messages: MessageMetadata[], enums: EnumMetadata[], imports: array<string, true>}> */
    private array $files = [];

    /**
     * @param array<string, string> $options File option thêm vào mọi file (vd go_package, java_multiple_files).
     * @param string|null $phpNamespace Prefix namespace cho class do protoc sinh ra (dùng khi build nội bộ).
     */
    public function __construct(
        private readonly array $options = [],
        private readonly ?string $phpNamespace = null,
    ) {}

    /**
     * @param ServiceMetadata[] $services
     * @param string[] $messages Thêm message không gắn với service nào.
     * @return array<string, string> tên file => nội dung
     */
    public function generate(array $services, array $messages = [], string $defaultPackage = 'app'): array
    {
        $this->packages = [];
        $this->files = [];

        foreach ($services as $service) {
            if (self::usesNativeMessages($service)) {
                // Service dựng từ .proto có sẵn (class protoc), giữ nguyên file .proto gốc.
                continue;
            }
            $this->files[$this->file($service->package)]['services'][] = $service;
            foreach ($service->methods as $method) {
                $this->collectMessage($method->requestClass, $service->package);
                $this->collectMessage($method->responseClass, $service->package);
            }
        }
        foreach ($messages as $class) {
            $this->collectMessage($class, $defaultPackage);
        }

        $output = [];
        foreach ($this->files as $package => $file) {
            $output[self::fileName($package)] = $this->render($package, $file);
        }
        \ksort($output);

        return $output;
    }

    public static function usesNativeMessages(ServiceMetadata $service): bool
    {
        foreach ($service->methods as $method) {
            if (MetadataFactory::isNative($method->requestClass) || MetadataFactory::isNative($method->responseClass)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Package đã gán cho từng class message trong lần generate gần nhất.
     *
     * @return array<string, string> class => package
     */
    public function messagePackages(): array
    {
        return \array_filter($this->packages, static fn ($_, $class) => !\enum_exists($class), \ARRAY_FILTER_USE_BOTH);
    }

    public static function fileName(string $package): string
    {
        return ($package === '' ? 'default' : $package) . '.proto';
    }

    private function file(string $package): string
    {
        $this->files[$package] ??= ['services' => [], 'messages' => [], 'enums' => [], 'imports' => []];
        return $package;
    }

    private function collectMessage(string $class, string $fallbackPackage): void
    {
        if (isset($this->packages[$class])) {
            return;
        }

        $metadata = MetadataFactory::forMessage($class);
        if ($metadata->wellKnown !== null) {
            $this->packages[$class] = $metadata->package;
            return;
        }

        $package = $metadata->package ?? $fallbackPackage;
        $this->packages[$class] = $package;
        $this->ensureUniqueName($package, $metadata->name, $class);
        $this->files[$this->file($package)]['messages'][] = $metadata;

        foreach ($metadata->fields as $field) {
            if ($field->type === Type::Message) {
                $this->collectMessage($field->class, $package);
            } elseif ($field->type === Type::Enum) {
                $this->collectEnum($field->class, $package);
            }
        }
    }

    private function collectEnum(string $class, string $fallbackPackage): void
    {
        if (isset($this->packages[$class])) {
            return;
        }
        $metadata = MetadataFactory::forEnum($class);
        $package = $metadata->package ?? $fallbackPackage;
        $this->packages[$class] = $package;
        $this->ensureUniqueName($package, $metadata->name, $class);
        $this->files[$this->file($package)]['enums'][] = $metadata;
    }

    private function ensureUniqueName(string $package, string $name, string $class): void
    {
        foreach ($this->packages as $other => $otherPackage) {
            if ($other === $class || $otherPackage !== $package) {
                continue;
            }
            $otherName = \enum_exists($other) ? MetadataFactory::forEnum($other)->name : MetadataFactory::forMessage($other)->name;
            if ($otherName === $name) {
                throw new MetadataException("Both [$other] and [$class] are named [$name] in package [$package]");
            }
        }
    }

    /**
     * Tên kiểu khi tham chiếu từ $fromPackage, đồng thời ghi nhận import cần thiết.
     */
    private function typeName(string $class, bool $isEnum, string $fromPackage): string
    {
        if ($isEnum) {
            $metadata = MetadataFactory::forEnum($class);
            $import = null;
        } else {
            $metadata = MetadataFactory::forMessage($class);
            $import = $metadata->wellKnown;
        }
        $package = $this->packages[$class];

        if ($package === $fromPackage) {
            return $metadata->name;
        }
        $this->files[$this->file($fromPackage)]['imports'][$import ?? self::fileName($package)] = true;
        return ($package === '' ? '' : "$package.") . $metadata->name;
    }

    private function fieldType(FieldMetadata $field, string $package): string
    {
        return match ($field->type) {
            Type::Message => $this->typeName($field->class, false, $package),
            Type::Enum => $this->typeName($field->class, true, $package),
            Type::Timestamp => $this->timestamp($package),
            default => $field->type->value,
        };
    }

    private function timestamp(string $package): string
    {
        $this->files[$this->file($package)]['imports']['google/protobuf/timestamp.proto'] = true;
        return 'google.protobuf.Timestamp';
    }

    private function render(string $package, array $file): string
    {
        // Render phần thân trước để thu thập import.
        $body = [];

        foreach ($file['services'] as $service) {
            $lines = $this->comment($service->comment);
            $lines[] = "service {$service->name} {";
            foreach ($service->methods as $method) {
                foreach ($this->comment($method->comment, '  ') as $line) {
                    $lines[] = $line;
                }
                $request = ($method->clientStreaming ? 'stream ' : '') . $this->typeName($method->requestClass, false, $package);
                $response = ($method->serverStreaming ? 'stream ' : '') . $this->typeName($method->responseClass, false, $package);
                $lines[] = "  rpc {$method->name}($request) returns ($response);";
            }
            $lines[] = '}';
            $body[] = \implode("\n", $lines);
        }

        foreach ($file['messages'] as $message) {
            $lines = $this->comment($message->comment);
            $lines[] = "message {$message->name} {";
            foreach ($message->fields as $field) {
                foreach ($this->comment($field->comment, '  ') as $line) {
                    $lines[] = $line;
                }
                $type = $this->fieldType($field, $package);
                $label = match (true) {
                    $field->isMap() => "map<{$field->keyType->value}, $type>",
                    $field->repeated => "repeated $type",
                    $field->isOptional() => "optional $type",
                    default => $type,
                };
                $options = $field->deprecated ? ' [deprecated = true]' : '';
                $lines[] = "  $label {$field->protoName} = {$field->number}$options;";
            }
            $lines[] = '}';
            $body[] = \implode("\n", $lines);
        }

        foreach ($file['enums'] as $enum) {
            $lines = $this->comment($enum->comment);
            $lines[] = "enum {$enum->name} {";
            foreach ($enum->values as $name => $value) {
                $lines[] = "  $name = $value;";
            }
            $lines[] = '}';
            $body[] = \implode("\n", $lines);
        }

        $header = [
            '// Code generated by Ryra gRPC. DO NOT EDIT.',
            '// Source: PHP classes with #[Service] / #[Message] attributes.',
            '',
            'syntax = "proto3";',
            '',
        ];
        if ($package !== '') {
            $header[] = "package $package;";
            $header[] = '';
        }

        $imports = \array_keys($this->files[$package]['imports']);
        \sort($imports);
        foreach ($imports as $import) {
            $header[] = "import \"$import\";";
        }
        if ($imports !== []) {
            $header[] = '';
        }

        $options = $this->options;
        if ($this->phpNamespace !== null) {
            $namespace = $this->phpNamespaceFor($package);
            $options['php_namespace'] = $namespace;
            $options['php_metadata_namespace'] = $namespace . '\\GPBMetadata';
        }
        foreach ($options as $key => $value) {
            $value = \is_bool($value) ? ($value ? 'true' : 'false') : '"' . \addcslashes((string) $value, '"\\') . '"';
            $header[] = "option $key = $value;";
        }
        if ($options !== []) {
            $header[] = '';
        }

        return \implode("\n", $header) . "\n" . \implode("\n\n", $body) . "\n";
    }

    public function phpNamespaceFor(string $package): string
    {
        $parts = \array_filter(\explode('.', $package), static fn ($part) => $part !== '');
        $parts = \array_map(static fn ($part) => \str_replace(' ', '', \ucwords(\str_replace('_', ' ', $part))), $parts);
        return \implode('\\', [$this->phpNamespace, ...$parts]);
    }

    /**
     * @return string[]
     */
    private function comment(?string $comment, string $indent = ''): array
    {
        if ($comment === null || $comment === '') {
            return [];
        }
        return \array_map(static fn ($line) => "$indent// " . \rtrim($line), \explode("\n", $comment));
    }
}
