<?php

namespace Ryra\Core\Grpc\Proto;

use Ryra\Core\Grpc\Exception\MetadataException;

/**
 * Parser file .proto (proto3) đủ dùng để sinh DTO: message, enum, service, oneof, map, optional,
 * import, comment. Không hỗ trợ proto2 / editions / extensions / group.
 *
 * Kết quả là array thuần:
 *
 *   file    = {path, package, imports[], messages[], enums[], services[]}
 *   message = {name, fullName, comment, fields[], messages[], enums[]}
 *   field   = {name, number, type, label: ''|'repeated'|'optional', keyType, oneof, deprecated, comment}
 *   enum    = {name, fullName, comment, values: [NAME => number]}
 *   service = {name, comment, rpcs: [{name, request, clientStreaming, response, serverStreaming, comment}]}
 */
final class ProtoParser
{
    /** @var array<int, array{0: string, 1: string, 2: int, 3: ?string}> [kind, value, line, comment] */
    private array $tokens = [];

    private int $pos = 0;

    private string $path = '';

    private string $package = '';

    public function parseFile(string $path): array
    {
        $source = @\file_get_contents($path);
        if ($source === false) {
            throw new MetadataException("Cannot read proto file [$path]");
        }
        return $this->parse($source, $path);
    }

    public function parse(string $source, string $path = 'input.proto'): array
    {
        $this->path = $path;
        $this->tokens = $this->tokenize($source);
        $this->pos = 0;
        $this->package = '';

        $file = ['path' => $path, 'package' => '', 'imports' => [], 'messages' => [], 'enums' => [], 'services' => []];

        while (!$this->atEnd()) {
            $comment = $this->peekComment();
            $word = $this->next();
            match ($word) {
                'syntax' => $this->parseSyntax(),
                'edition' => $this->fail('Protobuf editions are not supported, only proto3'),
                'package' => $file['package'] = $this->package = $this->parseFullIdent(true),
                'import' => $file['imports'][] = $this->parseImport(),
                'option' => $this->skipStatement(),
                'message' => $file['messages'][] = $this->parseMessage($this->package, $comment),
                'enum' => $file['enums'][] = $this->parseEnum($this->package, $comment),
                'service' => $file['services'][] = $this->parseService($comment),
                'extend' => $this->fail('extend is not supported'),
                ';' => null,
                default => $this->fail("Unexpected token [$word]"),
            };
            if ($word === 'package') {
                $this->expect(';');
            }
        }

        return $file;
    }

    // ------------------------------------------------------------------
    // Declarations
    // ------------------------------------------------------------------

    private function parseSyntax(): void
    {
        $this->expect('=');
        $syntax = $this->parseString();
        $this->expect(';');
        if ($syntax !== 'proto3') {
            $this->fail("Only proto3 is supported, got syntax \"$syntax\"");
        }
    }

    private function parseImport(): string
    {
        if (\in_array($this->peek(), ['public', 'weak'], true)) {
            $this->next();
        }
        $path = $this->parseString();
        $this->expect(';');
        return $path;
    }

    private function parseMessage(string $scope, ?string $comment): array
    {
        $name = $this->parseIdent();
        $fullName = self::join($scope, $name);
        $message = ['name' => $name, 'fullName' => $fullName, 'comment' => $comment, 'fields' => [], 'messages' => [], 'enums' => []];

        $this->expect('{');
        while (($token = $this->peek()) !== '}') {
            $comment = $this->peekComment();
            match ($token) {
                ';' => $this->next(),
                'option', 'reserved' => $this->skipStatement(),
                'message' => [$this->next(), $message['messages'][] = $this->parseMessage($fullName, $comment)],
                'enum' => [$this->next(), $message['enums'][] = $this->parseEnum($fullName, $comment)],
                'oneof' => [$this->next(), \array_push($message['fields'], ...$this->parseOneof($comment))],
                'extensions', 'extend' => $this->fail("$token is not supported (proto2 feature)"),
                'map' => $message['fields'][] = $this->parseMapField($comment),
                default => $message['fields'][] = $this->parseField($comment, null),
            };
        }
        $this->expect('}');

        return $message;
    }

    /**
     * Mỗi field trong oneof được sinh thành field nullable riêng (có presence), tương thích wire format.
     */
    private function parseOneof(?string $comment): array
    {
        $name = $this->parseIdent();
        $fields = [];
        $this->expect('{');
        while ($this->peek() !== '}') {
            if ($this->peek() === 'option') {
                $this->skipStatement();
                continue;
            }
            if ($this->peek() === ';') {
                $this->next();
                continue;
            }
            $fields[] = $this->parseField($this->peekComment(), $name);
        }
        $this->expect('}');
        return $fields;
    }

    private function parseField(?string $comment, ?string $oneof): array
    {
        $label = '';
        if (\in_array($this->peek(), ['repeated', 'optional', 'required'], true)) {
            $label = $this->next();
            if ($label === 'required') {
                $this->fail('required fields are not supported (proto2 feature)');
            }
            if ($oneof !== null) {
                $this->fail('Fields inside oneof cannot have a label');
            }
        }
        if ($this->peek() === 'group') {
            $this->fail('group is not supported (proto2 feature)');
        }

        $type = $this->parseFullIdent(false);
        $name = $this->parseIdent();
        $this->expect('=');
        $number = $this->parseInt();
        $deprecated = $this->parseFieldOptions();
        $this->expect(';');

        return [
            'name' => $name,
            'number' => $number,
            'type' => $type,
            'label' => $oneof !== null ? 'optional' : $label,
            'keyType' => null,
            'oneof' => $oneof,
            'deprecated' => $deprecated,
            'comment' => $comment,
        ];
    }

    private function parseMapField(?string $comment): array
    {
        $this->expect('map');
        $this->expect('<');
        $keyType = $this->parseIdent();
        $this->expect(',');
        $type = $this->parseFullIdent(false);
        $this->expect('>');
        $name = $this->parseIdent();
        $this->expect('=');
        $number = $this->parseInt();
        $deprecated = $this->parseFieldOptions();
        $this->expect(';');

        return [
            'name' => $name,
            'number' => $number,
            'type' => $type,
            'label' => 'map',
            'keyType' => $keyType,
            'oneof' => null,
            'deprecated' => $deprecated,
            'comment' => $comment,
        ];
    }

    /**
     * @return bool deprecated
     */
    private function parseFieldOptions(): bool
    {
        if ($this->peek() !== '[') {
            return false;
        }
        $this->next();
        $deprecated = false;
        while (true) {
            $name = $this->parseOptionName();
            $this->expect('=');
            $value = $this->parseConstant();
            if ($name === 'deprecated' && $value === 'true') {
                $deprecated = true;
            }
            if ($this->peek() === ',') {
                $this->next();
                continue;
            }
            break;
        }
        $this->expect(']');
        return $deprecated;
    }

    private function parseEnum(string $scope, ?string $comment): array
    {
        $name = $this->parseIdent();
        $enum = ['name' => $name, 'fullName' => self::join($scope, $name), 'comment' => $comment, 'values' => []];

        $this->expect('{');
        while (($token = $this->peek()) !== '}') {
            if ($token === ';') {
                $this->next();
                continue;
            }
            if ($token === 'option' || $token === 'reserved') {
                $this->skipStatement();
                continue;
            }
            $valueName = $this->parseIdent();
            $this->expect('=');
            $number = $this->parseInt();
            $this->parseFieldOptions();
            $this->expect(';');
            $enum['values'][$valueName] = $number;
        }
        $this->expect('}');

        return $enum;
    }

    private function parseService(?string $comment): array
    {
        $service = ['name' => $this->parseIdent(), 'comment' => $comment, 'rpcs' => []];

        $this->expect('{');
        while (($token = $this->peek()) !== '}') {
            if ($token === ';') {
                $this->next();
                continue;
            }
            if ($token === 'option') {
                $this->skipStatement();
                continue;
            }
            $rpcComment = $this->peekComment();
            $this->expect('rpc');
            $rpc = ['name' => $this->parseIdent(), 'comment' => $rpcComment];

            $this->expect('(');
            $rpc['clientStreaming'] = $this->peek() === 'stream' && $this->peek(1) !== ')' && $this->next() === 'stream';
            $rpc['request'] = $this->parseFullIdent(false);
            $this->expect(')');
            $this->expect('returns');
            $this->expect('(');
            $rpc['serverStreaming'] = $this->peek() === 'stream' && $this->peek(1) !== ')' && $this->next() === 'stream';
            $rpc['response'] = $this->parseFullIdent(false);
            $this->expect(')');

            if ($this->peek() === '{') {
                $this->skipBlock();
            } else {
                $this->expect(';');
            }
            $service['rpcs'][] = $rpc;
        }
        $this->expect('}');

        return $service;
    }

    // ------------------------------------------------------------------
    // Primitives
    // ------------------------------------------------------------------

    private function parseIdent(): string
    {
        [$kind, $value] = $this->tokens[$this->pos] ?? ['eof', '', 0, null];
        if ($kind !== 'ident') {
            $this->fail('Expected identifier, got ' . ($kind === 'eof' ? 'end of file' : "[$value]"));
        }
        $this->pos++;
        return $value;
    }

    /**
     * a.b.C hoặc .a.b.C (tên tuyệt đối).
     */
    private function parseFullIdent(bool $allowLeadingDot): string
    {
        $name = '';
        if ($this->peek() === '.') {
            $this->next();
            $name = '.';
        }
        $name .= $this->parseIdent();
        while ($this->peek() === '.') {
            $this->next();
            $name .= '.' . $this->parseIdent();
        }
        return $allowLeadingDot ? \ltrim($name, '.') : $name;
    }

    private function parseOptionName(): string
    {
        if ($this->peek() === '(') {
            $this->next();
            $name = '(' . $this->parseFullIdent(true) . ')';
            $this->expect(')');
        } else {
            $name = $this->parseIdent();
        }
        while ($this->peek() === '.') {
            $this->next();
            $name .= '.' . $this->parseIdent();
        }
        return $name;
    }

    private function parseConstant(): string
    {
        if ($this->peek() === '{') {
            $this->skipBlock();
            return '{}';
        }
        $sign = '';
        if ($this->peek() === '-' || $this->peek() === '+') {
            $sign = $this->next();
        }
        [$kind, $value] = $this->tokens[$this->pos] ?? ['eof', '', 0, null];
        if ($kind === 'eof') {
            $this->fail('Unexpected end of file');
        }
        $this->pos++;
        return $sign . $value;
    }

    private function parseInt(): int
    {
        $negative = false;
        if ($this->peek() === '-') {
            $this->next();
            $negative = true;
        }
        [$kind, $value] = $this->tokens[$this->pos] ?? ['eof', '', 0, null];
        if ($kind !== 'number' || !\preg_match('/^(0[xX][0-9a-fA-F]+|0[0-7]*|[1-9][0-9]*)$/', $value)) {
            $this->fail("Expected integer, got [$value]");
        }
        $this->pos++;
        $number = \intval($value, 0);
        return $negative ? -$number : $number;
    }

    private function parseString(): string
    {
        [$kind, $value] = $this->tokens[$this->pos] ?? ['eof', '', 0, null];
        if ($kind !== 'string') {
            $this->fail("Expected string, got [$value]");
        }
        $this->pos++;
        return $value;
    }

    private function skipStatement(): void
    {
        while (!$this->atEnd()) {
            $token = $this->next();
            if ($token === ';') {
                return;
            }
            if ($token === '{') {
                $this->pos--;
                $this->skipBlock();
            }
        }
    }

    private function skipBlock(): void
    {
        $this->expect('{');
        $depth = 1;
        while ($depth > 0) {
            if ($this->atEnd()) {
                $this->fail('Unexpected end of file, missing }');
            }
            $token = $this->next();
            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            }
        }
    }

    // ------------------------------------------------------------------
    // Token stream
    // ------------------------------------------------------------------

    private function atEnd(): bool
    {
        return $this->pos >= \count($this->tokens);
    }

    private function peek(int $ahead = 0): ?string
    {
        return $this->tokens[$this->pos + $ahead][1] ?? null;
    }

    private function peekComment(): ?string
    {
        return $this->tokens[$this->pos][3] ?? null;
    }

    private function next(): string
    {
        if ($this->atEnd()) {
            $this->fail('Unexpected end of file');
        }
        return $this->tokens[$this->pos++][1];
    }

    private function expect(string $token): void
    {
        $actual = $this->peek();
        if ($actual !== $token || ($this->tokens[$this->pos][0] === 'string')) {
            $this->fail("Expected [$token], got " . ($actual === null ? 'end of file' : "[$actual]"));
        }
        $this->pos++;
    }

    private function fail(string $message): never
    {
        $line = $this->tokens[\min($this->pos, \count($this->tokens) - 1)][2] ?? 0;
        throw new MetadataException("{$this->path}:$line: $message");
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: int, 3: ?string}>
     */
    private function tokenize(string $source): array
    {
        $tokens = [];
        $comments = [];
        $length = \strlen($source);
        $line = 1;
        $i = 0;

        while ($i < $length) {
            $char = $source[$i];

            if ($char === "\n") {
                $line++;
                $i++;
                // Dòng trống tách comment khỏi khai báo phía sau.
                if ($comments !== [] && \preg_match('/\G[ \t\r]*\n/', $source, $m, 0, $i)) {
                    $comments = [];
                }
                continue;
            }
            if (\ctype_space($char)) {
                $i++;
                continue;
            }
            if ($char === '/' && ($source[$i + 1] ?? '') === '/') {
                $end = \strpos($source, "\n", $i);
                $end = $end === false ? $length : $end;
                // Comment cuối dòng (sau khai báo trên cùng dòng) không thuộc về khai báo kế tiếp.
                if ($tokens === [] || $tokens[\count($tokens) - 1][2] !== $line) {
                    $comments[] = \trim(\substr($source, $i + 2, $end - $i - 2));
                }
                $i = $end;
                continue;
            }
            if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
                $end = \strpos($source, '*/', $i + 2);
                if ($end === false) {
                    throw new MetadataException("{$this->path}:$line: Unterminated block comment");
                }
                $text = \substr($source, $i + 2, $end - $i - 2);
                foreach (\explode("\n", $text) as $commentLine) {
                    $commentLine = \trim(\ltrim(\trim($commentLine), '*'));
                    if ($commentLine !== '') {
                        $comments[] = $commentLine;
                    }
                }
                $line += \substr_count($text, "\n");
                $i = $end + 2;
                continue;
            }

            $comment = $comments === [] ? null : \implode("\n", $comments);
            $comments = [];

            if (\ctype_alpha($char) || $char === '_') {
                \preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $source, $m, 0, $i);
                $tokens[] = ['ident', $m[0], $line, $comment];
                $i += \strlen($m[0]);
            } elseif (\ctype_digit($char)) {
                \preg_match('/\G(0[xX][0-9a-fA-F]+|[0-9]+(\.[0-9]*)?([eE][+-]?[0-9]+)?)/', $source, $m, 0, $i);
                $tokens[] = ['number', $m[0], $line, $comment];
                $i += \strlen($m[0]);
            } elseif ($char === '"' || $char === "'") {
                $value = '';
                $i++;
                while ($i < $length && $source[$i] !== $char) {
                    if ($source[$i] === '\\' && $i + 1 < $length) {
                        $i++;
                        $value .= match ($source[$i]) {
                            'n' => "\n",
                            't' => "\t",
                            default => $source[$i],
                        };
                    } else {
                        $value .= $source[$i];
                    }
                    $i++;
                }
                if ($i >= $length) {
                    throw new MetadataException("{$this->path}:$line: Unterminated string");
                }
                $i++;
                $tokens[] = ['string', $value, $line, $comment];
            } elseif (\str_contains('{}[]()<>=;,.:-+', $char)) {
                $tokens[] = ['symbol', $char, $line, $comment];
                $i++;
            } else {
                throw new MetadataException("{$this->path}:$line: Unexpected character [$char]");
            }
        }

        return $tokens;
    }

    private static function join(string $scope, string $name): string
    {
        return $scope === '' ? $name : "$scope.$name";
    }
}
