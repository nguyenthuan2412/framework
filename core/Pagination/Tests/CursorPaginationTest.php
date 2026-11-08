<?php

namespace Ryra\Core\Pagination\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Contracts\Database\Connection;
use Ryra\Core\Database\Query\Builder;
use Ryra\Core\Pagination\Cursor;
use Ryra\Core\Pagination\CursorPaginator;
use PDO;
use PDOStatement;

/**
 * Connection giả: bắt lại câu SQL cuối cùng và trả về tập dữ liệu định sẵn,
 * đủ để kiểm thử keyset pagination mà không cần DB thật.
 */
class FakeConnection implements Connection
{
    public string $lastQuery = '';
    public array $lastBindings = [];

    public function __construct(private array $rows = []) {}

    public function setRows(array $rows): void
    {
        $this->rows = $rows;
    }

    public function select(string $query, array $bindings = [], int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $this->lastQuery = $query;
        $this->lastBindings = $bindings;
        return $this->rows;
    }

    public function selectOne(string $query, array $bindings = [], int $mode = PDO::FETCH_DEFAULT, mixed ...$args): mixed { return $this->rows[0] ?? null; }
    public function insert(string $query, array $bindings = []): int { return 0; }
    public function insertGetId(string $query, string $name, array $bindings = []): string|false { return false; }
    public function delete(string $query, array $bindings = []): int { return 0; }
    public function update(string $query, array $bindings = []): int { return 0; }
    public function statement(string $query, array $bindings = []): PDOStatement|false { return false; }
    public function transaction(callable $callback, int $retries = 1): mixed { return $callback(); }
    public function beginTransaction(): bool { return true; }
    public function commit(): bool { return true; }
    public function rollBack(bool $rollbackAll = false): bool { return true; }
    public function inTransaction(): bool { return false; }
    public function getAttribute(int $attribute): mixed { return null; }
    public function setAttribute(int $attribute, mixed $value): static { return $this; }
    public function lastInsertId(string $name): string|false { return false; }
    public function ping(): bool { return true; }
    public function getPrefix(): ?string { return ''; }
    public function getLastQueryTime(): int { return 0; }
    public function config(): array { return []; }
    public function bindValues(PDOStatement $statement, array $bindings): static { return $this; }
    public function query(): Builder { return new Builder($this); }
    public function reconnect(): mixed { return null; }
    public function disconnect(): void {}
}

class CursorPaginationTest extends TestCase
{
    private function rows(int $count, int $startId = 1): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $id = $startId + $i;
            $rows[] = ['id' => $id, 'created_at' => '2026-01-' . str_pad((string) $id, 2, '0', STR_PAD_LEFT)];
        }
        return $rows;
    }

    // =========================================================================
    // Cursor encode / decode
    // =========================================================================

    public function test_cursor_encode_decode_roundtrip(): void
    {
        $cursor = new Cursor(['id' => 100, 'created_at' => '2026-05-05'], true);
        $decoded = Cursor::fromEncoded($cursor->encode());

        $this->assertSame(100, $decoded->parameter('id'));
        $this->assertSame('2026-05-05', $decoded->parameter('created_at'));
        $this->assertTrue($decoded->pointsToNextItems());
        $this->assertFalse($decoded->pointsToPreviousItems());
    }

    public function test_cursor_encode_is_url_safe(): void
    {
        $encoded = (new Cursor(['id' => 1], true))->encode();
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $encoded);
    }

    public function test_from_encoded_returns_null_for_empty_or_invalid(): void
    {
        $this->assertNull(Cursor::fromEncoded(null));
        $this->assertNull(Cursor::fromEncoded(''));
        $this->assertNull(Cursor::fromEncoded('not-a-valid-cursor'));
    }

    // =========================================================================
    // Query Builder: sinh SQL keyset
    // =========================================================================

    public function test_default_order_by_primary_key_when_none_given(): void
    {
        $conn = new FakeConnection($this->rows(3));
        $builder = (new Builder($conn))->from('posts');

        $builder->cursorPaginate(10, 'cursor', '', 'id');

        $this->assertStringContainsString('order by `id` asc', $conn->lastQuery);
        $this->assertStringContainsString('limit 11', $conn->lastQuery);
    }

    public function test_next_cursor_builds_keyset_where(): void
    {
        $conn = new FakeConnection($this->rows(3));
        $builder = (new Builder($conn))->from('posts')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'asc');

        $cursor = (new Cursor(['created_at' => '2026-05-05', 'id' => 42], true))->encode();
        $builder->cursorPaginate(10, 'cursor', $cursor, 'id');

        // (created_at < ?) OR (created_at = ? AND id > ?)
        $this->assertStringContainsString(
            '(`created_at` < ? or (`created_at` = ? and (`id` > ?)))',
            $conn->lastQuery
        );
        $this->assertStringContainsString('order by `created_at` desc, `id` asc', $conn->lastQuery);
        $this->assertSame(['2026-05-05', '2026-05-05', 42], $conn->lastBindings);
    }

    public function test_previous_cursor_reverses_order_direction(): void
    {
        $conn = new FakeConnection($this->rows(3));
        $builder = (new Builder($conn))->from('posts')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'asc');

        $cursor = (new Cursor(['created_at' => '2026-05-05', 'id' => 42], false))->encode();
        $builder->cursorPaginate(10, 'cursor', $cursor, 'id');

        // Chiều order bị đảo: desc->asc, asc->desc; comparison cũng đảo theo.
        $this->assertStringContainsString('order by `created_at` asc, `id` desc', $conn->lastQuery);
        $this->assertStringContainsString(
            '(`created_at` > ? or (`created_at` = ? and (`id` < ?)))',
            $conn->lastQuery
        );
    }

    // =========================================================================
    // CursorPaginator: cắt trang & sinh cursor
    // =========================================================================

    public function test_first_page_detects_more_and_slices(): void
    {
        // perPage=10, connection trả 11 dòng => còn trang kế.
        $conn = new FakeConnection($this->rows(11));
        $paginator = (new Builder($conn))->from('posts')
            ->orderBy('id', 'asc')
            ->cursorPaginate(10, 'cursor', '', 'id');

        $this->assertInstanceOf(CursorPaginator::class, $paginator);
        $this->assertCount(10, $paginator->getData());          // đã cắt bớt dòng thừa
        $this->assertNull($paginator->previousCursor());        // trang đầu, không có trang trước
        $this->assertNotNull($paginator->nextCursor());         // còn trang kế
        $this->assertTrue($paginator->nextCursor()->pointsToNextItems());
        // Cursor kế trỏ tới bản ghi cuối của trang (id = 10).
        $this->assertSame(10, $paginator->nextCursor()->parameter('id'));
    }

    public function test_last_page_has_no_next_cursor(): void
    {
        // Chỉ 5 dòng (< perPage+1) => hết dữ liệu.
        $conn = new FakeConnection($this->rows(5));
        $paginator = (new Builder($conn))->from('posts')
            ->orderBy('id', 'asc')
            ->cursorPaginate(10, 'cursor', '', 'id');

        $this->assertCount(5, $paginator->getData());
        $this->assertNull($paginator->nextCursor());
        $this->assertNull($paginator->previousCursor());
    }

    public function test_next_page_has_previous_cursor(): void
    {
        $conn = new FakeConnection($this->rows(11));
        $cursor = (new Cursor(['id' => 10], true))->encode();
        $paginator = (new Builder($conn))->from('posts')
            ->orderBy('id', 'asc')
            ->cursorPaginate(10, 'cursor', $cursor, 'id');

        $this->assertNotNull($paginator->previousCursor());
        $this->assertFalse($paginator->previousCursor()->pointsToNextItems());
        $this->assertNotNull($paginator->nextCursor());
    }
}
