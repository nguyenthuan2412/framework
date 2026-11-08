<?php

namespace Ryra\Core\AI\Document\Reader;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\AI\VectorStore\Document;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Đọc chữ trong file PDF.
 *
 * Dự án chưa có sẵn thư viện bóc chữ từ PDF (dompdf chỉ tạo PDF, không đọc),
 * nên reader này lần lượt thử:
 *
 *   1. smalot/pdfparser nếu đã cài (composer require smalot/pdfparser)
 *   2. lệnh pdftotext của poppler nếu có trên máy
 *
 * Không có cái nào thì báo lỗi rõ ràng thay vì trả về nội dung rỗng - vì kho
 * vector nhận tài liệu rỗng sẽ hỏng âm thầm, rất khó lần ra nguyên nhân.
 */
class PdfReader extends TextReader
{
    /**
     * @param array<string, mixed> $metadata
     * @param bool                 $perPage Tách mỗi trang thành một tài liệu riêng.
     */
    public function __construct(
        string $path,
        array $metadata = [],
        protected bool $perPage = false,
    ) {
        parent::__construct($path, $metadata);
    }

    public function read(): array
    {
        if (!is_file($this->path)) {
            throw new AIException(sprintf('Document file not found: "%s".', $this->path));
        }

        $pages = $this->extract();

        if (!$this->perPage) {
            $text = trim(implode(PHP_EOL . PHP_EOL, $pages));

            if ($text === '') {
                throw new AIException(sprintf(
                    'Không bóc được chữ nào từ "%s". Nhiều khả năng đây là PDF scan ảnh, cần OCR trước.',
                    $this->path,
                ));
            }

            return [new Document($this->id(), $text, $this->baseMetadata())];
        }

        $documents = [];

        foreach ($pages as $index => $page) {
            if (trim($page) === '') {
                continue;
            }

            $documents[] = new Document(
                sprintf('%s#%d', $this->id(), $index),
                trim($page),
                array_merge($this->baseMetadata(), ['page' => $index + 1]),
            );
        }

        return $documents;
    }

    /**
     * @return array<int, string>
     */
    protected function extract(): array
    {
        if (class_exists(Parser::class)) {
            return $this->extractWithParser();
        }

        if ($this->hasPdftotext()) {
            return $this->extractWithPdftotext();
        }

        throw new AIException(
            'Không có công cụ đọc PDF. Chạy "composer require smalot/pdfparser" '
            . 'hoặc cài poppler-utils để có lệnh pdftotext.'
        );
    }

    /**
     * @return array<int, string>
     */
    protected function extractWithParser(): array
    {
        try {
            $pdf = (new Parser())->parseFile($this->path);

            return array_map(
                static fn (object $page): string => $page->getText(),
                $pdf->getPages(),
            );
        } catch (Throwable $e) {
            throw new AIException(
                sprintf('Unable to parse PDF "%s": %s', $this->path, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * @return array<int, string>
     */
    protected function extractWithPdftotext(): array
    {
        $output = [];
        $status = 0;

        // -layout giữ được bố cục bảng biểu, đọc ra dễ hiểu hơn.
        exec(sprintf('pdftotext -layout %s -', escapeshellarg($this->path)), $output, $status);

        if ($status !== 0) {
            throw new AIException(sprintf('pdftotext failed on "%s".', $this->path));
        }

        // Ký tự form feed là dấu ngắt trang của pdftotext.
        return explode("\f", implode(PHP_EOL, $output));
    }

    protected function hasPdftotext(): bool
    {
        $output = [];
        $status = 0;

        exec('command -v pdftotext 2>/dev/null', $output, $status);

        return $status === 0 && $output !== [];
    }
}
