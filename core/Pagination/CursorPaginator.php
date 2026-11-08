<?php

namespace Ryra\Core\Pagination;

use IteratorAggregate;
use JsonSerializable;
use Traversable;
use Ryra\Core\App;
use Ryra\Core\Collection\Collection;

class CursorPaginator implements JsonSerializable, IteratorAggregate
{
    protected Collection $data;

    protected bool $hasMore = false;

    /**
     * @param iterable $data Kết quả truy vấn (đã lấy dư 1 bản ghi để phát hiện trang kế).
     * @param int $perPage Số bản ghi mỗi trang.
     * @param string[] $parameters Danh sách alias của các cột order, dùng để dựng cursor cho từng bản ghi.
     * @param Cursor|null $cursor Cursor hiện tại (null nếu là trang đầu).
     * @param string|null $cursorName Tên query param chứa cursor trên URL.
     */
    public function __construct(
        iterable $data,
        protected int $perPage,
        protected array $parameters,
        protected ?Cursor $cursor = null,
        protected ?string $cursorName = 'cursor'
    ) {
        $this->setData($data);
    }

    protected function setData(iterable $data): void
    {
        $data = $data instanceof Collection ? $data : new Collection($data);

        // Lấy dư 1 bản ghi so với perPage => còn dữ liệu để đi tiếp.
        $this->hasMore = $data->count() > $this->perPage;
        $data = $data->slice(0, $this->perPage)->values();

        // Khi cursor trỏ về trước, query đã đảo chiều order nên phải đảo lại kết quả.
        if (!is_null($this->cursor) && $this->cursor->pointsToPreviousItems()) {
            $data = $data->reverse()->values();
        }

        $this->data = $data;
    }

    public function getData(): Collection
    {
        return $this->data;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function hasMorePages(): bool
    {
        return (is_null($this->cursor) && $this->hasMore)
            || (!is_null($this->cursor) && $this->cursor->pointsToNextItems() && $this->hasMore)
            || (!is_null($this->cursor) && $this->cursor->pointsToPreviousItems());
    }

    public function nextCursor(): ?Cursor
    {
        if (
            (is_null($this->cursor) && !$this->hasMore)
            || (!is_null($this->cursor) && $this->cursor->pointsToNextItems() && !$this->hasMore)
        ) {
            return null;
        }

        $last = $this->data->last();
        if (empty($last)) {
            return null;
        }

        return $this->cursorForItem($last, true);
    }

    public function previousCursor(): ?Cursor
    {
        if (
            is_null($this->cursor)
            || ($this->cursor->pointsToPreviousItems() && !$this->hasMore)
        ) {
            return null;
        }

        $first = $this->data->first();
        if (empty($first)) {
            return null;
        }

        return $this->cursorForItem($first, false);
    }

    protected function cursorForItem(mixed $item, bool $pointsToNextItems): Cursor
    {
        $parameters = [];
        foreach ($this->parameters as $alias) {
            $parameters[$alias] = $item[$alias] ?? null;
        }

        return new Cursor($parameters, $pointsToNextItems);
    }

    protected function urlForCursor(?Cursor $cursor): ?string
    {
        if (is_null($cursor)) {
            return null;
        }

        return App::service('request')->fullUrlWithQuery([
            $this->cursorName => $cursor->encode(),
        ]);
    }

    public function toArray(): array
    {
        $request = App::service('request');
        $nextCursor = $this->nextCursor();
        $prevCursor = $this->previousCursor();

        return [
            'data' => $this->getData(),
            'path' => $request->url(),
            'per_page' => $this->getPerPage(),
            'next_cursor' => $nextCursor?->encode(),
            'next_page_url' => $this->urlForCursor($nextCursor),
            'prev_cursor' => $prevCursor?->encode(),
            'prev_page_url' => $this->urlForCursor($prevCursor),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function getIterator(): Traversable
    {
        return $this->getData();
    }

    public function __call(string $name, array $arguments): mixed
    {
        $this->data = $this->getData()->$name(...$arguments);
        return $this;
    }
}
