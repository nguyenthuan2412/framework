<?php

namespace Ryra\Core\AI\Document;

use Ryra\Core\AI\Document\Splitter\CharacterTextSplitter;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\Contracts\AI\DocumentReader;
use Ryra\Core\Contracts\AI\DocumentTransformer;
use Ryra\Core\Contracts\AI\VectorStore;

/**
 * Dây chuyền nạp tài liệu vào kho vector: đọc -> biến đổi -> ghi.
 *
 *   (new Ingestor($store))
 *       ->read(new DirectoryReader(base_path('docs/tour')))
 *       ->split(chunkSize: 800, chunkOverlap: 100)
 *       ->into('lich_trinh')
 *       ->run();
 */
class Ingestor
{
    /**
     * @var array<int, DocumentReader>
     */
    protected array $readers = [];

    /**
     * @var array<int, DocumentTransformer>
     */
    protected array $transformers = [];

    /**
     * @var array<int, Document>
     */
    protected array $documents = [];

    protected ?string $collection = null;

    /**
     * Số tài liệu nhúng mỗi lượt. Gửi cả nghìn tài liệu trong một request rất
     * dễ chạm giới hạn của nhà cung cấp.
     */
    protected int $batchSize = 50;

    public function __construct(protected VectorStore $store)
    {
    }

    public function read(DocumentReader ...$readers): static
    {
        array_push($this->readers, ...$readers);

        return $this;
    }

    /**
     * Thêm tài liệu dựng sẵn, không qua reader.
     *
     * @param array<int, Document>|Document $documents
     */
    public function add(array|Document $documents): static
    {
        array_push($this->documents, ...($documents instanceof Document ? [$documents] : $documents));

        return $this;
    }

    public function transform(DocumentTransformer ...$transformers): static
    {
        array_push($this->transformers, ...$transformers);

        return $this;
    }

    /**
     * Cắt nhỏ tài liệu - bước gần như luôn cần trước khi nhúng.
     */
    public function split(int $chunkSize = 1000, int $chunkOverlap = 200): static
    {
        return $this->transform(new CharacterTextSplitter($chunkSize, $chunkOverlap));
    }

    public function into(?string $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function batchSize(int $batchSize): static
    {
        if ($batchSize < 1) {
            throw new AIException("batchSize must be at least 1, {$batchSize} given.");
        }

        $this->batchSize = $batchSize;

        return $this;
    }

    /**
     * Chạy dây chuyền, trả về số tài liệu đã ghi vào kho.
     */
    public function run(): int
    {
        $documents = $this->collect();

        foreach (array_chunk($documents, $this->batchSize) as $batch) {
            $this->store->add($batch, $this->collection);
        }

        return count($documents);
    }

    /**
     * Chạy tới bước biến đổi rồi dừng - dùng để xem trước kết quả cắt nhỏ
     * trước khi tốn tiền gọi API nhúng.
     *
     * @return array<int, Document>
     */
    public function collect(): array
    {
        $documents = $this->documents;

        foreach ($this->readers as $reader) {
            array_push($documents, ...$reader->read());
        }

        foreach ($this->transformers as $transformer) {
            $documents = $transformer->transform($documents);
        }

        return array_values(array_filter(
            $documents,
            static fn (Document $document): bool => trim($document->content) !== '',
        ));
    }
}
