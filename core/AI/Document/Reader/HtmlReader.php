<?php

namespace Ryra\Core\AI\Document\Reader;

use DOMDocument;
use DOMXPath;
use Ryra\Core\AI\VectorStore\Document;

/**
 * Đọc file HTML, bóc lấy phần chữ và bỏ script/style/nav.
 */
class HtmlReader extends TextReader
{
    /**
     * Những thẻ không mang nội dung cần nhúng.
     */
    protected const NOISE_TAGS = ['script', 'style', 'noscript', 'nav', 'footer', 'header'];

    public function read(): array
    {
        $html = $this->contents();

        $dom = new DOMDocument();
        // HTML thật gần như luôn có lỗi cú pháp, tắt cảnh báo của libxml.
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);

        foreach (static::NOISE_TAGS as $tag) {
            foreach (iterator_to_array($xpath->query("//{$tag}") ?: []) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $title = $xpath->query('//title')?->item(0)?->textContent;
        $text = $dom->textContent ?? '';

        // Gom khoảng trắng thừa do thụt lề HTML sinh ra.
        $text = trim((string) preg_replace('/\n{3,}/', "\n\n", (string) preg_replace('/[ \t]+/', ' ', $text)));

        return [new Document(
            $this->id(),
            $text,
            array_merge($this->baseMetadata(), array_filter(['title' => $title !== null ? trim($title) : null])),
        )];
    }
}
