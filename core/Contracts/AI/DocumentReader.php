<?php

namespace Ryra\Core\Contracts\AI;

use Ryra\Core\AI\VectorStore\Document;

interface DocumentReader
{
    /**
     * Đọc nguồn dữ liệu và trả về danh sách tài liệu thô, chưa cắt nhỏ.
     *
     * @return array<int, Document>
     */
    public function read(): array;
}
