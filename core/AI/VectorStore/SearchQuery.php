<?php

namespace Ryra\Core\AI\VectorStore;

use Ryra\Core\AI\Exceptions\AIException;

class SearchQuery
{
    protected ?string $text = null;

    /**
     * @var array<int, float>|null
     */
    protected ?array $vector = null;

    protected int $topK = 4;

    /**
     * Ngưỡng điểm tương đồng, tài liệu thấp hơn sẽ bị loại.
     * 0 nghĩa là không lọc.
     */
    protected float $threshold = 0.0;

    /**
     * @var array<string, mixed>
     */
    protected array $filter = [];

    protected ?string $collection = null;

    public static function make(?string $text = null): static
    {
        $query = new static();

        return $text === null ? $query : $query->text($text);
    }

    /**
     * @param array<int, float> $vector
     */
    public static function fromVector(array $vector): static
    {
        return (new static())->vector($vector);
    }

    public function text(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    /**
     * @param array<int, float> $vector
     */
    public function vector(array $vector): static
    {
        $this->vector = $vector;
        return $this;
    }

    /**
     * @return array<int, float>|null
     */
    public function getVector(): ?array
    {
        return $this->vector;
    }

    public function topK(int $topK): static
    {
        if ($topK < 1) {
            throw new AIException("topK must be at least 1, {$topK} given.");
        }

        $this->topK = $topK;
        return $this;
    }

    public function getTopK(): int
    {
        return $this->topK;
    }

    public function threshold(float $threshold): static
    {
        $this->threshold = $threshold;
        return $this;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    /**
     * Lọc theo metadata, so khớp bằng nhau. Giá trị mảng nghĩa là "thuộc một trong".
     *
     * @param array<string, mixed> $filter
     */
    public function filter(array $filter): static
    {
        $this->filter = array_merge($this->filter, $filter);
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    public function collection(?string $collection): static
    {
        $this->collection = $collection;
        return $this;
    }

    public function getCollection(): ?string
    {
        return $this->collection;
    }

    public function guard(): void
    {
        if ($this->text === null && $this->vector === null) {
            throw new AIException('SearchQuery cần có text hoặc vector để tìm kiếm.');
        }
    }
}
