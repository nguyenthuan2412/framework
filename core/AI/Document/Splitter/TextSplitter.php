<?php

namespace Ryra\Core\AI\Document\Splitter;

use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\Contracts\AI\DocumentTransformer;

/**
 * Cắt tài liệu dài thành nhiều đoạn nhỏ trước khi nhúng.
 *
 * Bắt buộc phải làm bước này: nhúng nguyên một file dài vào một vector sẽ làm
 * loãng ngữ nghĩa, tìm kiếm gần như vô dụng. Đoạn nhỏ cũng giúp phần ngữ cảnh
 * nhồi vào prompt gọn hơn.
 */
abstract class TextSplitter implements DocumentTransformer
{
    public function transform(array $documents): array
    {
        $chunks = [];

        foreach ($documents as $document) {
            $pieces = $this->splitText($document->content);
            $total = count($pieces);

            foreach ($pieces as $index => $piece) {
                $chunks[] = new Document(
                    // Giữ liên hệ với tài liệu gốc để còn truy ngược nguồn.
                    sprintf('%s#%d', $document->id, $index),
                    $piece,
                    array_merge($document->metadata, [
                        'parent_id' => $document->id,
                        'chunk_index' => $index,
                        'chunk_total' => $total,
                    ]),
                );
            }
        }

        return $chunks;
    }

    /**
     * @return array<int, string>
     */
    abstract public function splitText(string $text): array;
}
