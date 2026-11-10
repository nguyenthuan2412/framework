<?php

namespace Ryra\Core\Grpc\Protobuf;

use Ryra\Core\Grpc\Metadata\FieldMetadata;
use Ryra\Core\Grpc\Metadata\MetadataFactory;

/**
 * Sinh encoder/decoder wire format protobuf chuyên biệt cho từng class #[Message].
 *
 * Code sinh ra đọc/ghi thẳng property của DTO <-> bytes, không đi qua class do protoc sinh ra
 * (google/protobuf), nên không tốn chi phí dựng object trung gian cho mỗi message / message con.
 * Code chỉ được sinh từ metadata của class (không phụ thuộc dữ liệu request) và cache theo worker.
 *
 * @internal
 */
final class WireCompiler
{
    private const W = '\\' . Wire::class;

    public function __construct(private readonly Codec $codec) {}

    // ------------------------------------------------------------------
    // Encoder: DTO -> bytes
    // ------------------------------------------------------------------

    /**
     * @return \Closure(object): string
     */
    public function encoder(string $class): \Closure
    {
        $F = MetadataFactory::forMessage($class)->fields;
        $c = $this->codec;
        $E = &$c->wireEncoders();

        $code = "static function (object \$m) use (\$c, \$F, &\$E): string {\n    \$o = '';\n";
        foreach (self::byNumber($F) as $name => $field) {
            $code .= $this->encodeField($name, $field);
        }
        $code .= "    return \$o;\n}";

        return $this->load('E', $class, '$c, $F, &$E', $code)($c, $F, $E);
    }

    private function encodeField(string $name, FieldMetadata $field): string
    {
        $ref = \var_export($name, true);
        $read = "\$m->{$name} ?? null";

        if ($field->isMap()) {
            [$keyWire, $keyEnc] = self::scalarEncoder($field->keyType, '$k');
            [$valueWire, $valueEnc, $valuePrep] = $this->valueEncoder($field, '$x', $ref);
            $entry = self::literal(Wire::tag(1, $keyWire)) . " . $keyEnc . " . self::literal(Wire::tag(2, $valueWire)) . " . $valueEnc";
            $keyCast = self::castExpr($field->keyType, '$k');
            return "    if ((\$v = $read) !== null) {\n"
                . "        foreach (\$v as \$k => \$x) { \$k = $keyCast; $valuePrep\$e = $entry; \$o .= "
                . self::literal(Wire::tag($field->number, Wire::LEN)) . ' . ' . self::len('$e') . " . \$e; }\n"
                . "    }\n";
        }

        if ($field->repeated) {
            [$wire, $enc, $prep] = $this->valueEncoder($field, '$x', $ref);
            if ($wire !== Wire::LEN) {
                // Kiểu số: packed (mặc định của proto3).
                return "    if ((\$v = $read) !== null) {\n"
                    . "        \$p = '';\n        foreach (\$v as \$x) { $prep\$p .= $enc; }\n"
                    . "        if (\$p !== '') { \$o .= " . self::literal(Wire::tag($field->number, Wire::LEN)) . ' . ' . self::len('$p') . " . \$p; }\n"
                    . "    }\n";
            }
            return "    if ((\$v = $read) !== null) {\n"
                . "        foreach (\$v as \$x) { $prep\$o .= " . self::literal(Wire::tag($field->number, Wire::LEN)) . " . $enc; }\n"
                . "    }\n";
        }

        [$wire, $enc, $prep] = $this->valueEncoder($field, '$v', $ref);
        $tag = self::literal(Wire::tag($field->number, $wire));

        if ($field->type === Type::Message || $field->type === Type::Timestamp || $field->isOptional()) {
            // Có presence: gửi khi khác null, kể cả giá trị mặc định.
            return "    if ((\$v = $read) !== null) { $prep\$o .= $tag . $enc; }\n";
        }

        // proto3 implicit presence: bỏ qua giá trị mặc định.
        $default = self::zero($field->type === Type::Enum ? Type::Int32 : $field->type);
        return "    if ((\$v = $read) !== null) { $prep if (\$v !== $default) { \$o .= $tag . $enc; } }\n";
    }

    /**
     * @return array{0: int, 1: string, 2: string} [wire type, biểu thức bytes, câu lệnh chuẩn bị $var]
     */
    private function valueEncoder(FieldMetadata $field, string $var, string $ref): array
    {
        return match ($field->type) {
            Type::Message => [
                Wire::LEN,
                self::len('$s') . ' . $s',
                "if (!$var instanceof " . self::fqcn($field->class) . ") { $var = \$c->ensureObject(\$F[$ref], $var); } \$s = (\$E[{$var}::class] ?? \$c->wireEncoder({$var}::class))($var); ",
            ],
            Type::Timestamp => [Wire::LEN, self::len('$s') . ' . $s', "\$s = " . self::W . "::timestamp($var); "],
            Type::Enum => [Wire::VARINT, self::varint($var), "$var = $var instanceof \\BackedEnum ? {$var}->value : (int) $var; "],
            default => [...self::scalarEncoder($field->type, $var), "$var = " . self::castExpr($field->type, $var) . '; '],
        };
    }

    /**
     * @return array{0: int, 1: string} [wire type, biểu thức bytes (không gồm tag)]
     */
    private static function scalarEncoder(Type $type, string $var): array
    {
        return match ($type) {
            Type::Int32, Type::Int64, Type::Uint32, Type::Uint64 => [Wire::VARINT, self::varint($var)],
            Type::Sint32 => [Wire::VARINT, self::varint("(\$z = (($var << 1) ^ ($var >> 31)) & 0xFFFFFFFF)", '$z')],
            Type::Sint64 => [Wire::VARINT, self::varint("(\$z = ($var << 1) ^ ($var >> 63))", '$z')],
            Type::Bool => [Wire::VARINT, "($var ? \"\\x01\" : \"\\x00\")"],
            Type::Fixed32, Type::Sfixed32 => [Wire::I32, "\\pack('V', $var)"],
            Type::Float => [Wire::I32, "\\pack('g', $var)"],
            Type::Fixed64, Type::Sfixed64 => [Wire::I64, "\\pack('P', $var)"],
            Type::Double => [Wire::I64, "\\pack('e', $var)"],
            Type::String, Type::Bytes => [Wire::LEN, self::len($var) . " . $var"],
            default => throw new \LogicException("Type {$type->value} is not a scalar"),
        };
    }

    private static function castExpr(Type $type, string $var): string
    {
        return match ($type->phpType()) {
            'int' => "(int) $var",
            'float' => "(float) $var",
            'bool' => "(bool) $var",
            default => "(string) $var",
        };
    }

    // ------------------------------------------------------------------
    // Decoder: bytes -> DTO
    // ------------------------------------------------------------------

    /**
     * @return \Closure(string): object
     */
    public function decoder(string $class): \Closure
    {
        $F = MetadataFactory::forMessage($class)->fields;
        $D = [];
        $H = [];
        $R = new \ReflectionClass($class);
        $P = $R->hasMethod('__clone') ? null : $R->newInstanceWithoutConstructor();
        $c = $this->codec;
        $DEC = &$c->wireDecoders();
        $W = self::W;

        $init = '';
        $cases = '';
        $assign = '';
        $index = 0;
        foreach (self::byNumber($F) as $name => $field) {
            $var = '$v' . $index++;
            $ref = \var_export($name, true);
            [$fieldInit, $fieldCases, $value] = $this->decodeField($field, $var, $ref, $D, $name);
            $init .= "    $var = $fieldInit;\n";
            $cases .= $fieldCases;

            if ($field->declaringClass === $class) {
                $assign .= "    \$o->{$name} = $value;\n";
            } else {
                // Property khai báo ở class cha: gán qua hydrator đúng scope (cần cho readonly).
                $H[$field->declaringClass] ??= Codec::hydrator($field->declaringClass);
                $assign .= '    $H[' . \var_export($field->declaringClass, true) . "](\$o, [$ref => $value]);\n";
            }
        }

        $code = "static function (string \$b) use (\$c, \$F, \$D, \$R, \$P, \$H, &\$DEC): object {\n"
            . "    \$i = 0;\n    \$n = \\strlen(\$b);\n$init"
            . "    while (\$i < \$n) {\n"
            . "        \$t = \\ord(\$b[\$i++]);\n"
            . "        if (\$t > 0x7F) { \$i--; \$t = $W::readVarint(\$b, \$i); }\n"
            . "        switch (\$t) {\n$cases"
            . "            default: $W::skip(\$b, \$i, \$t, \$n);\n"
            . "        }\n"
            . "    }\n"
            . "    if (\$i !== \$n) { throw $W::truncated(); }\n"
            . "    \$o = " . ($P !== null ? 'clone $P' : '$R->newInstanceWithoutConstructor()') . ";\n$assign"
            . "    return \$o;\n}";

        $decoder = $this->load('D', $class, '$c, $F, $D, $R, $P, $H, &$DEC', $code)($c, $F, $D, $R, $P, $H, $DEC);

        return \Closure::bind($decoder, null, $class);
    }

    /**
     * @return array{0: string, 1: string, 2: string} [giá trị khởi tạo, các case của switch, biểu thức gán vào property]
     */
    private function decodeField(FieldMetadata $field, string $var, string $ref, array &$D, string $name): array
    {
        $number = $field->number;
        $class = $field->class !== null ? \var_export($field->class, true) : 'null';

        if ($field->isMap()) {
            // Mỗi entry là message con {1: key, 2: value}, field thiếu thì lấy giá trị mặc định.
            [$keyWire, $keyRead] = self::scalarDecoder($field->keyType);
            $W = self::W;
            if ($field->type === Type::Message || $field->type === Type::Timestamp) {
                $valueWire = Wire::LEN;
                $valueRead = self::readLen() . ' $y = \\substr($b, $i, $l); $i += $l;';
                $valueInit = "''";
                $valueOut = $field->type === Type::Message ? self::decodeMessageExpr('$y', $field->class) : "\$c->decodeTimestamp(\$y, $class)";
            } else {
                $isEnum = $field->type === Type::Enum;
                [$valueWire, $valueRead] = self::scalarDecoder($isEnum ? Type::Int32 : $field->type);
                $valueRead .= ' $y = $x;';
                $valueInit = self::zero($isEnum ? Type::Int32 : $field->type);
                $valueOut = $isEnum ? self::enumExpr($field, '$y', $ref) : '$y';
            }
            $keyTag = (1 << 3) | $keyWire;
            $valueTag = (2 << 3) | $valueWire;
            $case = $this->caseLen($number, "\$end = \$i + \$l; \$k = " . self::zero($field->keyType) . "; \$y = $valueInit;"
                . " while (\$i < \$end) { \$t = \\ord(\$b[\$i++]); if (\$t > 0x7F) { \$i--; \$t = $W::readVarint(\$b, \$i); }"
                . " if (\$t === $keyTag) { $keyRead \$k = \$x; } elseif (\$t === $valueTag) { $valueRead } else { $W::skip(\$b, \$i, \$t, \$end); } }"
                . " if (\$i !== \$end) { throw $W::truncated(); } {$var}[\$k] = $valueOut;");
            return ['[]', $case, $var];
        }

        if ($field->type === Type::Message || $field->type === Type::Timestamp) {
            $decode = $field->type === Type::Message
                ? fn (string $bytes) => self::decodeMessageExpr($bytes, $field->class)
                : fn (string $bytes) => "\$c->decodeTimestamp($bytes, $class)";

            if ($field->repeated) {
                return ['[]', $this->caseLen($number, "{$var}[] = " . $decode('\\substr($b, $i, $l)') . '; $i += $l;'), $var];
            }
            // Message xuất hiện nhiều lần thì merge (= nối bytes rồi parse một lần).
            $case = $this->caseLen($number, "$var = ($var ?? '') . \\substr(\$b, \$i, \$l); \$i += \$l;");
            $empty = $field->nullable ? 'null' : $decode("''");
            return ['null', $case, "($var === null ? $empty : " . $decode($var) . ')'];
        }

        $isEnum = $field->type === Type::Enum;
        $scalarType = $isEnum ? Type::Int32 : $field->type;
        [$wire, $read] = self::scalarDecoder($scalarType);
        $convert = fn (string $x) => $isEnum ? self::enumExpr($field, $x, $ref) : $x;

        if ($field->repeated) {
            $append = "{$var}[] = " . $convert('$x') . ';';
            $case = '';
            if ($wire !== Wire::LEN) {
                // Packed.
                $case .= $this->caseLen($number, "\$end = \$i + \$l; while (\$i < \$end) { $read $append } if (\$i !== \$end) { throw " . self::W . '::truncated(); }');
            }
            $case .= '            case ' . (($number << 3) | $wire) . ": $read $append break;\n";
            return ['[]', $case, $var];
        }

        $case = '            case ' . (($number << 3) | $wire) . ": $read $var = \$x; break;\n";

        if ($field->isOptional()) {
            $fallback = 'null';
            if ($field->hasDefault) {
                $D[$name] = $field->default;
                $fallback = "\$D[$ref]";
            }
            return ['null', $case, "($var === null ? $fallback : " . $convert($var) . ')'];
        }

        return [self::zero($scalarType), $case, $convert($var)];
    }

    private static function decodeMessageExpr(string $bytes, string $messageClass): string
    {
        $class = \var_export($messageClass, true);
        if (self::isRecursive($messageClass)) {
            // Type đệ quy: giới hạn độ sâu để payload lồng quá sâu không làm tràn stack worker.
            return "\$c->decodeNested($bytes, $class)";
        }
        return "(\$DEC[$class] ?? \$c->wireDecoder($class))($bytes)";
    }

    /**
     * Message có thể (gián tiếp) chứa lại chính nó không.
     */
    private static function isRecursive(string $class): bool
    {
        static $cache = [];
        if (isset($cache[$class])) {
            return $cache[$class];
        }

        $stack = [$class];
        $seen = [];
        while ($stack) {
            foreach (MetadataFactory::forMessage(\array_pop($stack))->fields as $field) {
                if ($field->type !== Type::Message) {
                    continue;
                }
                if ($field->class === $class) {
                    return $cache[$class] = true;
                }
                if (!isset($seen[$field->class])) {
                    $seen[$field->class] = true;
                    $stack[] = $field->class;
                }
            }
        }
        return $cache[$class] = false;
    }

    private static function enumExpr(FieldMetadata $field, string $value, string $ref): string
    {
        return "(" . self::fqcn($field->class) . "::tryFrom($value) ?? \$c->toEnum(\$F[$ref], $value))";
    }

    private static function zero(Type $type): string
    {
        return match ($type->phpType()) {
            'int' => '0',
            'float' => '0.0',
            'bool' => 'false',
            default => "''",
        };
    }

    /**
     * @return array{0: int, 1: string} [wire type, câu lệnh đọc giá trị vào $x]
     */
    private static function scalarDecoder(Type $type): array
    {
        $W = self::W;
        $varint = "\$x = \\ord(\$b[\$i++] ?? \"\\0\"); if (\$x > 0x7F) { \$i--; \$x = $W::readVarint(\$b, \$i); }";
        $fixed = static fn (string $format, int $size, string $after = '') =>
            "if (\$i + $size > \$n) { throw $W::truncated(); } \$x = \\unpack('$format', \$b, \$i)[1]; \$i += $size;$after";

        return match ($type) {
            Type::Int64, Type::Uint64 => [Wire::VARINT, $varint],
            Type::Int32 => [Wire::VARINT, "$varint \$x = \$x << 32 >> 32;"],
            Type::Uint32 => [Wire::VARINT, "$varint \$x &= 0xFFFFFFFF;"],
            Type::Sint32 => [Wire::VARINT, "$varint \$x = ((\$x & 0xFFFFFFFF) >> 1) ^ -(\$x & 1);"],
            Type::Sint64 => [Wire::VARINT, "$varint \$x = ((\$x >> 1) & \\PHP_INT_MAX) ^ -(\$x & 1);"],
            Type::Bool => [Wire::VARINT, "$varint \$x = \$x !== 0;"],
            Type::Fixed32 => [Wire::I32, $fixed('V', 4)],
            Type::Sfixed32 => [Wire::I32, $fixed('V', 4, ' $x = $x << 32 >> 32;')],
            Type::Float => [Wire::I32, $fixed('g', 4)],
            Type::Fixed64, Type::Sfixed64 => [Wire::I64, $fixed('P', 8)],
            Type::Double => [Wire::I64, $fixed('e', 8)],
            Type::String, Type::Bytes => [Wire::LEN, self::readLen() . ' $x = \\substr($b, $i, $l); $i += $l;'],
            default => throw new \LogicException("Type {$type->value} is not a scalar"),
        };
    }

    private function caseLen(int $number, string $body): string
    {
        return '            case ' . (($number << 3) | Wire::LEN) . ': ' . self::readLen() . " $body break;\n";
    }

    private static function readLen(): string
    {
        $W = self::W;
        return "\$l = \\ord(\$b[\$i++] ?? \"\\0\"); if (\$l > 0x7F) { \$i--; \$l = $W::readVarint(\$b, \$i); } if (\$l < 0 || \$i + \$l > \$n) { throw $W::truncated(); }";
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Biến code sinh ra thành factory closure nhận các biến mà code cần `use`.
     *
     * Ghi code ra file rồi include, vì code chạy qua eval() không được OPcache tối ưu và JIT gần như
     * không áp dụng (đo được: include nhanh hơn eval ~25% với encode, ~30% với decode khi bật JIT).
     * Không ghi được file (hoặc Codec không có cacheDir) thì quay về eval().
     *
     * @return \Closure(mixed ...): \Closure
     */
    private function load(string $kind, string $class, string $params, string $code): \Closure
    {
        $code = "static function ($params): \\Closure {\n    return $code;\n}";

        $dir = $this->codec->cacheDir();
        if ($dir !== null && ($file = self::write($dir, $kind, $class, $code)) !== null) {
            return include $file;
        }

        return eval("return $code;");
    }

    /**
     * Ghi code vào {$dir}/{$kind}_{hash class}_{hash code}.php nếu chưa có, trả về đường dẫn file.
     * Code chỉ phụ thuộc metadata của class nên hash code đổi khi class (hoặc WireCompiler) đổi.
     */
    private static function write(string $dir, string $kind, string $class, string $code): ?string
    {
        $prefix = $kind . '_' . \substr(\hash('xxh128', $class), 0, 16) . '_';
        $file = $dir . \DIRECTORY_SEPARATOR . $prefix . \substr(\hash('xxh128', $code), 0, 16) . '.php';
        if (\is_file($file)) {
            return $file;
        }

        if (!\is_dir($dir) && !@\mkdir($dir, 0755, true) && !\is_dir($dir)) {
            return null;
        }

        // Xoá file của phiên bản cũ (class đã bị sửa).
        foreach (\glob($dir . \DIRECTORY_SEPARATOR . $prefix . '*.php') ?: [] as $old) {
            @\unlink($old);
        }

        // Ghi ra file tạm rồi rename: nhiều worker cùng sinh một file cũng không đọc phải file ghi dở.
        $tmp = $file . '.' . \getmypid() . '.' . \bin2hex(\random_bytes(4)) . '.tmp';
        if (@\file_put_contents($tmp, "<?php\n\n// Sinh tự động bởi " . self::class . " cho $class. Không sửa tay.\n\nreturn $code;\n") === false) {
            return null;
        }
        // OPcache bỏ qua file mới sửa trong opcache.file_update_protection giây (mặc định 2): lùi mtime để
        // worker vừa sinh file cũng được cache/JIT ngay (closure sống suốt vòng đời worker).
        @\touch($tmp, \time() - 60);
        if (!@\rename($tmp, $file)) {
            @\unlink($tmp);
            return \is_file($file) ? $file : null;
        }

        return $file;
    }

    /**
     * @param array<string, FieldMetadata> $fields
     * @return array<string, FieldMetadata> Sắp theo field number (giống thứ tự protoc serialize).
     */
    private static function byNumber(array $fields): array
    {
        \uasort($fields, static fn (FieldMetadata $a, FieldMetadata $b) => $a->number <=> $b->number);
        return $fields;
    }

    /**
     * Varint inline cho số nhỏ (1-2 byte), số lớn/âm gọi Wire::varint().
     * $assign (nếu có) là biểu thức gán vào $var, được tính đúng một lần.
     */
    private static function varint(string $assign, ?string $var = null): string
    {
        $var ??= $assign;
        return "(($assign) < 0x80 && $var >= 0 ? \\chr($var) : ($var < 0x4000 && $var > 0 ? \\chr(($var & 0x7F) | 0x80) . \\chr($var >> 7) : " . self::W . "::varint($var)))";
    }

    private static function len(string $var): string
    {
        return "(\\strlen($var) < 0x80 ? \\chr(\\strlen($var)) : " . self::W . "::varint(\\strlen($var)))";
    }

    private static function literal(string $bytes): string
    {
        return '"' . \implode('', \array_map(static fn (string $c) => \sprintf('\\x%02X', \ord($c)), \str_split($bytes))) . '"';
    }

    private static function fqcn(?string $class): string
    {
        return '\\' . \ltrim((string) $class, '\\');
    }
}
