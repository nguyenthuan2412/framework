<?php

namespace Ryra\Core\Contracts\AI;

use Ryra\Core\AI\VectorStore\Document;

interface DocumentTransformer
{
    /**
     * Biến đổi danh sách tài liệu: cắt nhỏ, làm sạch, bổ sung metadata...
     *
     * @param  array<int, Document> $documents
     * @return array<int, Document>
     */
    public function transform(array $documents): array;
}
