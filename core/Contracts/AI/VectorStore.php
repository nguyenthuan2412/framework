<?php

namespace Ryra\Core\Contracts\AI;

use Ryra\Core\AI\VectorStore\Document;
use Ryra\Core\AI\VectorStore\SearchQuery;

interface VectorStore
{
    /**
     * Thêm (hoặc ghi đè theo id) các tài liệu vào kho vector.
     * Tài liệu chưa có embedding sẽ được nhúng tự động.
     *
     * @param array<int, Document>|Document $documents
     * @param string|null                   $collection Bỏ trống thì dùng collection mặc định trong config.
     */
    public function add(array|Document $documents, ?string $collection = null): void;

    /**
     * Xoá tài liệu theo danh sách id.
     *
     * @param array<int, string> $ids
     */
    public function delete(array $ids, ?string $collection = null): void;

    /**
     * Tìm các tài liệu gần nhất với câu truy vấn.
     *
     * @return array<int, Document> Đã sắp xếp theo điểm tương đồng giảm dần.
     */
    public function search(SearchQuery $query): array;

    /**
     * Xoá toàn bộ tài liệu của một collection.
     */
    public function clear(?string $collection = null): void;
}
