<?php

namespace Ryra\Core\AI\Document\Reader;

use Ryra\Core\AI\VectorStore\Document;

/**
 * Đọc file Markdown.
 *
 * Mặc định tách theo tiêu đề: mỗi mục trở thành một tài liệu riêng và mang theo
 * tên tiêu đề trong metadata. Cách này cho kết quả tìm kiếm tốt hơn hẳn so với
 * cắt mù theo độ dài, vì mỗi mục thường đã là một đơn vị ngữ nghĩa trọn vẹn.
 */
class MarkdownReader extends TextReader
{
    /**
     * @param array<string, mixed> $metadata
     * @param int                  $splitLevel Mức tiêu đề dùng để tách (1 = "#", 2 = "##"). 0 = không tách.
     */
    public function __construct(
        string $path,
        array $metadata = [],
        protected int $splitLevel = 2,
    ) {
        parent::__construct($path, $metadata);
    }

    public function read(): array
    {
        $content = $this->contents();

        if ($this->splitLevel < 1) {
            return [new Document($this->id(), trim($content), $this->baseMetadata())];
        }

        $documents = [];

        foreach ($this->sections($content) as $index => $section) {
            if (trim($section['content']) === '') {
                continue;
            }

            $documents[] = new Document(
                sprintf('%s#%d', $this->id(), $index),
                trim($section['content']),
                array_merge($this->baseMetadata(), array_filter([
                    'heading' => $section['heading'],
                ], static fn (mixed $value): bool => $value !== null)),
            );
        }

        return $documents;
    }

    /**
     * @return array<int, array{heading: string|null, content: string}>
     */
    protected function sections(string $content): array
    {
        $marker = str_repeat('#', $this->splitLevel);
        $sections = [];
        $current = ['heading' => null, 'content' => ''];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (preg_match('/^' . $marker . '\s+(.*)$/', $line, $matches) === 1) {
                if (trim($current['content']) !== '' || $current['heading'] !== null) {
                    $sections[] = $current;
                }

                // Giữ lại dòng tiêu đề trong nội dung để model biết đang nói về mục nào.
                $current = ['heading' => trim($matches[1]), 'content' => $line . PHP_EOL];
                continue;
            }

            $current['content'] .= $line . PHP_EOL;
        }

        $sections[] = $current;

        return $sections;
    }
}
