<?php

namespace Ryra\Core\AI\Document\Splitter;

use Ryra\Core\AI\Exceptions\AIException;

/**
 * Cắt theo ký tự nhưng ưu tiên giữ nguyên cấu trúc văn bản.
 *
 * Thuật toán đệ quy: thử tách theo đoạn văn trước, đoạn nào vẫn quá dài thì
 * hạ xuống tách theo dòng, rồi theo câu, theo từ. Nhờ vậy đoạn cắt ra hiếm khi
 * đứt ngang giữa câu như cách cắt cứng theo độ dài.
 *
 * Các đoạn liền nhau chồng lấn một phần (overlap) để câu nằm ở ranh giới không
 * bị mất ngữ cảnh.
 */
class CharacterTextSplitter extends TextSplitter
{
    /**
     * Thứ tự ưu tiên: đoạn văn -> xuống dòng -> câu -> mệnh đề -> từ -> ký tự.
     */
    public const DEFAULT_SEPARATORS = ["\n\n", "\n", '. ', ', ', ' ', ''];

    /**
     * @var callable(string):int
     */
    protected $lengthFunction;

    /**
     * @param array<int, string>          $separators
     * @param (callable(string):int)|null $lengthFunction Cách đo độ dài, mặc định đếm ký tự.
     */
    public function __construct(
        protected int $chunkSize = 1000,
        protected int $chunkOverlap = 200,
        protected array $separators = self::DEFAULT_SEPARATORS,
        ?callable $lengthFunction = null,
    ) {
        if ($chunkSize < 1) {
            throw new AIException("chunkSize must be greater than 0, {$chunkSize} given.");
        }

        if ($chunkOverlap < 0 || $chunkOverlap >= $chunkSize) {
            throw new AIException(
                "chunkOverlap ({$chunkOverlap}) must be between 0 and chunkSize ({$chunkSize})."
            );
        }

        $this->lengthFunction = $lengthFunction ?? static fn (string $text): int => mb_strlen($text);
    }

    public function splitText(string $text): array
    {
        $text = trim($text);

        return $text === '' ? [] : $this->split($text, $this->separators);
    }

    /**
     * @param  array<int, string> $separators
     * @return array<int, string>
     */
    protected function split(string $text, array $separators): array
    {
        [$separator, $remaining] = $this->pickSeparator($text, $separators);

        $pieces = $separator === ''
            ? mb_str_split($text)
            : explode($separator, $text);

        $chunks = [];
        $pending = [];

        foreach ($pieces as $piece) {
            if ($this->length($piece) <= $this->chunkSize) {
                $pending[] = $piece;
                continue;
            }

            // Gộp phần đang chờ trước khi đi sâu vào đoạn quá dài.
            if ($pending !== []) {
                array_push($chunks, ...$this->merge($pending, $separator));
                $pending = [];
            }

            $chunks = $remaining === []
                // Hết cách tách: đành giữ nguyên đoạn dài còn hơn cắt mất chữ.
                ? [...$chunks, $piece]
                : [...$chunks, ...$this->split($piece, $remaining)];
        }

        if ($pending !== []) {
            array_push($chunks, ...$this->merge($pending, $separator));
        }

        return array_values(array_filter($chunks, static fn (string $chunk): bool => trim($chunk) !== ''));
    }

    /**
     * Chọn dấu tách nhỏ nhất còn xuất hiện trong văn bản.
     *
     * @param  array<int, string> $separators
     * @return array{0: string, 1: array<int, string>}
     */
    protected function pickSeparator(string $text, array $separators): array
    {
        foreach ($separators as $index => $separator) {
            if ($separator === '' || str_contains($text, $separator)) {
                return [$separator, array_slice($separators, $index + 1)];
            }
        }

        return ['', []];
    }

    /**
     * Ghép các mảnh nhỏ thành đoạn sát trần chunkSize, giữ phần chồng lấn ở đầu
     * đoạn kế tiếp.
     *
     * @param  array<int, string> $pieces
     * @return array<int, string>
     */
    protected function merge(array $pieces, string $separator): array
    {
        $chunks = [];
        $current = [];

        foreach ($pieces as $piece) {
            $candidate = [...$current, $piece];

            if ($current !== [] && $this->length(implode($separator, $candidate)) > $this->chunkSize) {
                $chunks[] = implode($separator, $current);
                $current = $this->tailForOverlap($current, $separator);
                $candidate = [...$current, $piece];
            }

            $current = $candidate;
        }

        if ($current !== []) {
            $chunks[] = implode($separator, $current);
        }

        return $chunks;
    }

    /**
     * Giữ lại phần đuôi của đoạn vừa chốt để làm phần chồng lấn cho đoạn kế tiếp.
     *
     * @param  array<int, string> $pieces
     * @return array<int, string>
     */
    protected function tailForOverlap(array $pieces, string $separator): array
    {
        if ($this->chunkOverlap === 0) {
            return [];
        }

        $tail = [];

        // Lấy ngược từ cuối lên cho tới khi đủ lượng chồng lấn mong muốn.
        foreach (array_reverse($pieces) as $piece) {
            $candidate = [$piece, ...$tail];

            if ($tail !== [] && $this->length(implode($separator, $candidate)) > $this->chunkOverlap) {
                break;
            }

            $tail = $candidate;

            if ($this->length(implode($separator, $tail)) >= $this->chunkOverlap) {
                break;
            }
        }

        return $tail;
    }

    protected function length(string $text): int
    {
        return ($this->lengthFunction)($text);
    }
}
