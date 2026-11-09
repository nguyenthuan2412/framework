<?php

namespace Ryra\Core\Grpc\Proto;

use Ryra\Core\Grpc\Exception\MetadataException;
use Ryra\Core\Grpc\Metadata\MetadataFactory;

/**
 * Sinh class DTO #[Message], enum #[Enum] và interface #[Service] từ file .proto,
 * để gọi / implement service của hệ thống khác mà không cần protoc hay google/protobuf.
 *
 *   $files = (new DtoGenerator('Ryra\Modules\Billing\Grpc'))->generate(['proto/payment.proto']);
 *   // ['Payment/V1/Invoice.php' => '<?php ...', ...]
 *
 * Mỗi package proto thành một sub namespace (payment.v1 => Payment\V1). Message lồng nhau được
 * làm phẳng (Order.Item => OrderItem). Field trong oneof thành field nullable riêng.
 */
final class DtoGenerator
{
    private const SCALARS = [
        'double' => ['float', 'Double'], 'float' => ['float', 'Float'],
        'int32' => ['int', 'Int32'], 'int64' => ['int', 'Int64'],
        'uint32' => ['int', 'Uint32'], 'uint64' => ['int', 'Uint64'],
        'sint32' => ['int', 'Sint32'], 'sint64' => ['int', 'Sint64'],
        'fixed32' => ['int', 'Fixed32'], 'fixed64' => ['int', 'Fixed64'],
        'sfixed32' => ['int', 'Sfixed32'], 'sfixed64' => ['int', 'Sfixed64'],
        'bool' => ['bool', 'Bool'], 'string' => ['string', 'String'], 'bytes' => ['string', 'Bytes'],
    ];

    /** Kiểu proto mà PHP suy ra được, không cần ghi type trong #[Field]. */
    private const INFERRED = ['double' => true, 'int64' => true, 'bool' => true, 'string' => true];

    private const RESERVED = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const',
        'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final',
        'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include',
        'instanceof', 'insteadof', 'int', 'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace',
        'never', 'new', 'null', 'object', 'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly',
        'require', 'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use',
        'var', 'void', 'while', 'xor', 'yield',
    ];

    private const EMPTY_CLASS = 'Ryra\Core\Grpc\Messages\EmptyMessage';

    /** @var array<string, array> fullName proto => {kind, def, package, class, file} */
    private array $symbols = [];

    /** @var array<string, array> path => file đã parse */
    private array $files = [];

    /**
     * @param string $namespace Namespace gốc cho class sinh ra.
     * @param string[] $includePaths Thư mục tìm file import (ngoài thư mục của file đang import).
     */
    public function __construct(
        private readonly string $namespace,
        private readonly array $includePaths = [],
    ) {}

    /**
     * @param string[] $protoFiles
     * @return array<string, string> đường dẫn tương đối (theo namespace gốc) => nội dung file PHP
     */
    public function generate(array $protoFiles): array
    {
        $this->files = [];
        $this->symbols = [];

        $parser = new ProtoParser();
        $queue = \array_map(static fn (string $path) => [$path, null], $protoFiles);
        while ($queue) {
            [$path, $from] = \array_shift($queue);
            $real = $from === null ? self::realpath($path) : $this->resolveImport($path, $from);
            if ($real === null || isset($this->files[$real])) {
                continue;
            }
            $file = $parser->parseFile($real);
            $this->files[$real] = $file;
            foreach ($file['imports'] as $import) {
                $queue[] = [$import, $real];
            }
        }

        foreach ($this->files as $file) {
            $this->collect($file, $file['messages'], $file['enums']);
        }

        $output = [];
        foreach ($this->files as $path => $file) {
            $source = $this->displayPath($path);
            foreach ($this->symbolsOf($path) as $symbol) {
                $content = $symbol['kind'] === 'message'
                    ? $this->renderMessage($symbol, $source)
                    : $this->renderEnum($symbol, $source);
                $output[$this->relativePath($symbol['class'])] = $content;
            }
            foreach ($file['services'] as $service) {
                $class = $this->className($file['package'], $service['name'], 'Service');
                $clashes = \array_filter($this->symbols, static fn (array $symbol) => \strcasecmp($symbol['class'], $class) === 0);
                if ($clashes !== []) {
                    throw new MetadataException("Service [{$service['name']}] clashes with a message/enum of the same name in package [{$file['package']}]");
                }
                $output[$this->relativePath($class)] = $this->renderService($service, $file['package'], $class, $source);
            }
        }
        \ksort($output);

        return $output;
    }

    // ------------------------------------------------------------------
    // Load & symbols
    // ------------------------------------------------------------------

    private function resolveImport(string $import, string $from): ?string
    {
        if (\str_starts_with($import, 'google/protobuf/')) {
            // Well-known types: Timestamp / Empty được framework hỗ trợ sẵn, kiểu khác báo lỗi khi dùng.
            return null;
        }
        foreach ([\dirname($from), ...$this->includePaths] as $dir) {
            $path = \rtrim($dir, '/') . '/' . $import;
            if (\is_file($path)) {
                return self::realpath($path);
            }
        }
        throw new MetadataException("Cannot find import \"$import\" (from {$this->displayPath($from)}). Add its root directory with --include");
    }

    private static function realpath(string $path): string
    {
        $real = \realpath($path);
        if ($real === false || !\is_file($real)) {
            throw new MetadataException("Proto file [$path] does not exist");
        }
        return $real;
    }

    private function collect(array $file, array $messages, array $enums): void
    {
        foreach ($messages as $message) {
            $this->addSymbol('message', $message, $file);
            $this->collect($file, $message['messages'], $message['enums']);
        }
        foreach ($enums as $enum) {
            $this->addSymbol('enum', $enum, $file);
        }
    }

    private function addSymbol(string $kind, array $def, array $file): void
    {
        $fullName = $def['fullName'];
        if (isset($this->symbols[$fullName])) {
            throw new MetadataException("Type [$fullName] is defined twice ({$this->displayPath($file['path'])})");
        }
        $package = $file['package'];
        $local = $package === '' ? $fullName : \substr($fullName, \strlen($package) + 1);
        $class = $this->className($package, \str_replace('.', '', $local), $kind === 'message' ? 'Message' : 'Enum');

        foreach ($this->symbols as $other) {
            if (\strcasecmp($other['class'], $class) === 0) {
                throw new MetadataException("Types [{$other['def']['fullName']}] and [$fullName] would both generate class $class");
            }
        }

        $this->symbols[$fullName] = [
            'kind' => $kind,
            'def' => $def,
            'package' => $package,
            'protoName' => $local,
            'class' => $class,
            'file' => $file['path'],
        ];
    }

    private function symbolsOf(string $path): array
    {
        return \array_filter($this->symbols, static fn (array $symbol) => $symbol['file'] === $path);
    }

    /**
     * @return array{kind: string, class?: string, type?: string, symbol?: array}
     *         kind: scalar | message | enum | timestamp | empty
     */
    private function resolve(string $type, string $scope, string $where): array
    {
        if (isset(self::SCALARS[$type])) {
            return ['kind' => 'scalar', 'type' => $type];
        }

        $candidates = [];
        if (\str_starts_with($type, '.')) {
            $candidates[] = \substr($type, 1);
        } else {
            $parts = $scope === '' ? [] : \explode('.', $scope);
            for ($i = \count($parts); $i >= 0; $i--) {
                $candidates[] = \implode('.', [...\array_slice($parts, 0, $i), $type]);
            }
        }

        foreach ($candidates as $name) {
            if ($name === 'google.protobuf.Timestamp') {
                return ['kind' => 'timestamp'];
            }
            if ($name === 'google.protobuf.Empty') {
                return ['kind' => 'empty', 'class' => self::EMPTY_CLASS];
            }
            if (isset($this->symbols[$name])) {
                $symbol = $this->symbols[$name];
                return ['kind' => $symbol['kind'], 'class' => $symbol['class'], 'symbol' => $symbol];
            }
        }

        if (\str_starts_with(\ltrim($type, '.'), 'google.protobuf.')) {
            throw new MetadataException("$where: well-known type [$type] is not supported (only google.protobuf.Timestamp and google.protobuf.Empty)");
        }
        throw new MetadataException("$where: unknown type [$type]");
    }

    // ------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------

    private function renderMessage(array $symbol, string $source): string
    {
        $message = $symbol['def'];
        $namespace = self::namespaceOf($symbol['class']);
        $short = self::shortName($symbol['class']);
        $where = "{$source} message {$message['fullName']}";

        $attribute = ['package' => $symbol['package']];
        if ($short !== $symbol['protoName'] && !\str_contains($symbol['protoName'], '.')) {
            $attribute['name'] = $symbol['protoName'];
        }
        if ($message['comment'] !== null) {
            $attribute['comment'] = $message['comment'];
        }

        $params = [];
        $usesType = false;
        $usedProperties = [];
        foreach ($message['fields'] as $field) {
            [$param, $needsType] = $this->renderField($field, $message['fullName'], $namespace, "$where field {$field['name']}");
            $property = self::propertyName($field['name']);
            if (isset($usedProperties[$property])) {
                throw new MetadataException("$where: fields [{$usedProperties[$property]}] and [{$field['name']}] map to the same property \$$property");
            }
            $usedProperties[$property] = $field['name'];
            $params[] = $param;
            $usesType = $usesType || $needsType;
        }

        $uses = ['Ryra\Core\Grpc\Attributes\Message'];
        if ($params !== []) {
            $uses[] = 'Ryra\Core\Grpc\Attributes\Field';
        }
        if ($usesType) {
            $uses[] = 'Ryra\Core\Grpc\Protobuf\Type';
        }

        $body = $params === []
            ? "final class $short\n{\n}\n"
            : "final class $short\n{\n    public function __construct(\n" . \implode('', \array_map(static fn ($p) => "        $p,\n", $params)) . "    ) {}\n}\n";

        return $this->header($namespace, $uses, $source) . self::attribute('Message', $attribute) . "\n" . $body;
    }

    /**
     * @return array{0: string, 1: bool} [khai báo tham số constructor, có dùng Type::]
     */
    private function renderField(array $field, string $scope, string $namespace, string $where): array
    {
        $resolved = $this->resolve($field['type'], $scope, $where);
        $property = self::propertyName($field['name']);
        $args = [(string) $field['number']];
        $usesType = false;

        $classRef = fn (string $class) => $this->classRef($class, $namespace);
        $elementType = function () use ($resolved, $classRef, &$usesType): string {
            return match ($resolved['kind']) {
                'scalar' => ($usesType = true) ? 'Type::' . self::SCALARS[$resolved['type']][1] : '',
                'timestamp' => '\DateTimeImmutable::class',
                default => $classRef($resolved['class']) . '::class',
            };
        };

        if ($field['label'] === 'map') {
            if (!isset(self::SCALARS[$field['keyType']]) || \in_array($field['keyType'], ['double', 'float', 'bytes'], true)) {
                throw new MetadataException("$where: invalid map key type [{$field['keyType']}]");
            }
            $args[] = 'type: ' . $elementType();
            $args[] = 'key: Type::' . self::SCALARS[$field['keyType']][1];
            $usesType = true;
            $declaration = "public array \$$property = []";
        } elseif ($field['label'] === 'repeated') {
            $args[] = 'type: ' . $elementType();
            $declaration = "public array \$$property = []";
        } else {
            $optional = $field['label'] === 'optional';
            switch ($resolved['kind']) {
                case 'scalar':
                    [$phpType, $typeCase] = self::SCALARS[$resolved['type']];
                    if (!isset(self::INFERRED[$resolved['type']])) {
                        $args[] = "type: Type::$typeCase";
                        $usesType = true;
                    }
                    $default = match ($phpType) {
                        'int' => '0',
                        'float' => '0.0',
                        'bool' => 'false',
                        default => "''",
                    };
                    $declaration = $optional ? "public ?$phpType \$$property = null" : "public $phpType \$$property = $default";
                    break;
                case 'enum':
                    $enum = $classRef($resolved['class']);
                    if ($optional) {
                        $declaration = "public ?$enum \$$property = null";
                    } else {
                        $declaration = "public $enum \$$property = $enum::" . $this->enumDefaultCase($resolved['symbol']);
                    }
                    break;
                case 'timestamp':
                    $declaration = "public ?\DateTimeImmutable \$$property = null";
                    break;
                default:
                    $declaration = 'public ?' . $classRef($resolved['class']) . " \$$property = null";
            }
        }

        if (MetadataFactory::snake($property) !== $field['name']) {
            $args[] = 'name: ' . self::export($field['name']);
        }
        if ($field['deprecated']) {
            $args[] = 'deprecated: true';
        }
        $comment = $field['comment'];
        if ($field['oneof'] !== null) {
            $comment = \trim(($comment ?? '') . "\noneof {$field['oneof']}");
        }
        if ($comment !== null) {
            $args[] = 'comment: ' . self::export($comment);
        }

        return ['#[Field(' . \implode(', ', $args) . ')] ' . $declaration, $usesType];
    }

    private function renderEnum(array $symbol, string $source): string
    {
        $enum = $symbol['def'];
        $namespace = self::namespaceOf($symbol['class']);
        $short = self::shortName($symbol['class']);

        [$cases, $prefix] = $this->enumCases($symbol);

        $attribute = ['package' => $symbol['package']];
        $exportName = \str_replace('.', '', $symbol['protoName']);
        if ($short !== $exportName) {
            $attribute['name'] = $exportName;
        }
        if (!$prefix) {
            $attribute['prefix'] = false;
        }
        if ($enum['comment'] !== null) {
            $attribute['comment'] = $enum['comment'];
        }

        $lines = '';
        foreach ($cases as $case => $value) {
            $lines .= "    case $case = $value;\n";
        }

        return $this->header($namespace, ['Ryra\Core\Grpc\Attributes\Enum'], $source)
            . self::attribute('Enum', $attribute) . "\nenum $short: int\n{\n$lines}\n";
    }

    /**
     * @return array{0: array<string, int>, 1: bool} [case PHP => value, có dùng prefix tên enum không]
     */
    private function enumCases(array $symbol): array
    {
        static $cache = [];
        $key = $symbol['def']['fullName'];
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $values = [];
        foreach ($symbol['def']['values'] as $name => $value) {
            // Alias (allow_alias) trùng value: PHP backed enum không cho trùng, giữ tên đầu tiên.
            if (!\in_array($value, $values, true)) {
                $values[$name] = $value;
            }
        }
        if ($values === []) {
            throw new MetadataException("Enum [{$symbol['def']['fullName']}] has no values");
        }

        // Ryra xuất tên value = PREFIX_ + UPPER_SNAKE(case), PREFIX = UPPER_SNAKE(tên enum).
        $exportName = \str_replace('.', '', $symbol['protoName']);
        $prefix = \strtoupper(MetadataFactory::snake($exportName)) . '_';

        foreach ([true, false] as $usePrefix) {
            $cases = [];
            foreach ($values as $name => $value) {
                if ($usePrefix && !\str_starts_with($name, $prefix)) {
                    continue 2;
                }
                $case = self::studly(\strtolower($usePrefix ? \substr($name, \strlen($prefix)) : $name));
                if ($case === '' || \ctype_digit($case[0]) || \strtolower($case) === 'class'
                    || \strtoupper(MetadataFactory::snake($case)) !== ($usePrefix ? \substr($name, \strlen($prefix)) : $name)
                    || isset($cases[$case])) {
                    continue 2;
                }
                $cases[$case] = $value;
            }
            return $cache[$key] = [$cases, $usePrefix];
        }

        // Tên value không theo UPPER_SNAKE_CASE: vẫn sinh case hợp lệ. Wire format chỉ dùng số
        // nên vẫn tương thích, chỉ khác tên khi xuất lại .proto bằng `grpc:proto`.
        $cases = [];
        foreach ($values as $name => $value) {
            $case = self::studly(\strtolower(\preg_replace('/[^A-Za-z0-9_]/', '_', $name)));
            if ($case === '' || \ctype_digit($case[0]) || \strtolower($case) === 'class') {
                $case = 'Value' . $case;
            }
            $base = $case;
            for ($i = 2; isset($cases[$case]); $i++) {
                $case = $base . $i;
            }
            $cases[$case] = $value;
        }
        return $cache[$key] = [$cases, false];
    }

    private function enumDefaultCase(array $symbol): string
    {
        [$cases] = $this->enumCases($symbol);
        $zero = \array_search(0, $cases, true);
        return $zero !== false ? $zero : \array_key_first($cases);
    }

    private function renderService(array $service, string $package, string $class, string $source): string
    {
        $namespace = self::namespaceOf($class);
        $short = self::shortName($class);
        $where = "$source service {$service['name']}";

        $attribute = ['name' => $service['name'], 'package' => $package];
        if ($service['comment'] !== null) {
            $attribute['comment'] = $service['comment'];
        }

        $methods = [];
        $usesRpc = false;
        foreach ($service['rpcs'] as $rpc) {
            $request = $this->resolve($rpc['request'], $package, "$where rpc {$rpc['name']}");
            $response = $this->resolve($rpc['response'], $package, "$where rpc {$rpc['name']}");
            foreach ([$request, $response] as $type) {
                if (!\in_array($type['kind'], ['message', 'empty'], true)) {
                    throw new MetadataException("$where rpc {$rpc['name']}: request/response must be a message type");
                }
            }

            $method = \lcfirst($rpc['name']);
            $rpcArgs = [];
            if (\ucfirst($method) !== $rpc['name']) {
                $rpcArgs['name'] = $rpc['name'];
            }

            $params = [];
            if ($rpc['clientStreaming']) {
                $rpcArgs['request'] = $this->classRef($request['class'], $namespace) . '::class';
                $params[] = 'iterable $request';
            } elseif ($request['kind'] !== 'empty') {
                $params[] = $this->classRef($request['class'], $namespace) . ' $request';
            }
            $params[] = 'ServerContext $context';

            if ($rpc['serverStreaming']) {
                $rpcArgs['response'] = $this->classRef($response['class'], $namespace) . '::class';
                $return = 'iterable';
            } elseif ($response['kind'] === 'empty') {
                $return = 'void';
            } else {
                $return = $this->classRef($response['class'], $namespace);
            }

            if ($rpc['comment'] !== null) {
                $rpcArgs['comment'] = self::export($rpc['comment']);
            }
            if (isset($rpcArgs['name'])) {
                $rpcArgs['name'] = self::export($rpcArgs['name']);
            }

            $code = '';
            if ($rpcArgs !== []) {
                $usesRpc = true;
                $code .= '    #[Rpc(' . \implode(', ', \array_map(static fn ($k, $v) => "$k: $v", \array_keys($rpcArgs), $rpcArgs)) . ")]\n";
            }
            $code .= "    public function $method(" . \implode(', ', $params) . "): $return;\n";
            $methods[] = $code;
        }

        $uses = ['Ryra\Core\Grpc\Attributes\Service', 'Ryra\Core\Grpc\Server\ServerContext'];
        if ($usesRpc) {
            $uses[] = 'Ryra\Core\Grpc\Attributes\Rpc';
        }

        return $this->header($namespace, $uses, $source)
            . self::attribute('Service', $attribute) . "\ninterface $short\n{\n" . \implode("\n", $methods) . "}\n";
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function header(string $namespace, array $uses, string $source): string
    {
        \sort($uses);
        return "<?php\n\n// Code generated by `php ryra grpc:dto` from $source. DO NOT EDIT.\n\nnamespace $namespace;\n\n"
            . \implode('', \array_map(static fn ($use) => "use $use;\n", $uses)) . "\n";
    }

    private static function attribute(string $name, array $args): string
    {
        $parts = [];
        foreach ($args as $key => $value) {
            $parts[] = "$key: " . self::export($value);
        }
        return "#[$name(" . \implode(', ', $parts) . ')]';
    }

    private static function export(mixed $value): string
    {
        return \is_string($value) ? "'" . \addcslashes($value, "'\\") . "'" : \var_export($value, true);
    }

    private function className(string $package, string $name, string $suffix): string
    {
        $short = self::studly($name);
        if (\in_array(\strtolower($short), self::RESERVED, true)) {
            $short .= $suffix;
        }
        $parts = \array_map(self::studly(...), \array_filter(\explode('.', $package), static fn ($p) => $p !== ''));
        return \implode('\\', [\trim($this->namespace, '\\'), ...$parts, $short]);
    }

    private function classRef(string $class, string $fromNamespace): string
    {
        return self::namespaceOf($class) === $fromNamespace ? self::shortName($class) : '\\' . $class;
    }

    private function relativePath(string $class): string
    {
        $relative = \substr($class, \strlen(\trim($this->namespace, '\\')) + 1);
        return \str_replace('\\', '/', $relative) . '.php';
    }

    private function displayPath(string $path): string
    {
        $cwd = \getcwd() ?: '';
        return $cwd !== '' && \str_starts_with($path, $cwd . '/') ? \substr($path, \strlen($cwd) + 1) : \basename($path);
    }

    private static function propertyName(string $field): string
    {
        if (\strtoupper($field) === $field) {
            $field = \strtolower($field);
        }
        return \lcfirst(self::studly($field));
    }

    private static function studly(string $value): string
    {
        return \implode('', \array_map(\ucfirst(...), \preg_split('/[_\-\s]+/', $value, -1, \PREG_SPLIT_NO_EMPTY)));
    }

    private static function namespaceOf(string $class): string
    {
        return \substr($class, 0, (int) \strrpos($class, '\\'));
    }

    private static function shortName(string $class): string
    {
        return \substr($class, (int) \strrpos($class, '\\') + 1);
    }
}
