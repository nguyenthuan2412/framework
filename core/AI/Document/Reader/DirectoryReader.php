<?php

namespace Ryra\Core\AI\Document\Reader;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\Contracts\AI\DocumentReader;
use SplFileInfo;

/**
 * Quét cả thư mục và chọn reader phù hợp theo phần mở rộng của từng file.
 */
class DirectoryReader implements DocumentReader
{
    /**
     * @var array<string, class-string<DocumentReader>>
     */
    protected static array $readers = [
        'txt' => TextReader::class,
        'log' => TextReader::class,
        'md' => MarkdownReader::class,
        'markdown' => MarkdownReader::class,
        'html' => HtmlReader::class,
        'htm' => HtmlReader::class,
        'json' => JsonReader::class,
        'csv' => CsvReader::class,
        'xlsx' => ExcelReader::class,
        'xls' => ExcelReader::class,
        'pdf' => PdfReader::class,
    ];

    /**
     * @param array<string, mixed>    $metadata
     * @param array<int, string>|null $extensions Chỉ đọc các phần mở rộng này, null = mọi loại hỗ trợ.
     */
    public function __construct(
        protected string $path,
        protected array $metadata = [],
        protected bool $recursive = true,
        protected ?array $extensions = null,
    ) {}

    /**
     * Đăng ký reader cho định dạng mới.
     *
     * @param class-string<DocumentReader> $reader
     */
    public static function register(string $extension, string $reader): void
    {
        static::$readers[strtolower($extension)] = $reader;
    }

    public function read(): array
    {
        if (!is_dir($this->path)) {
            throw new AIException(sprintf('Directory not found: "%s".', $this->path));
        }

        $documents = [];

        foreach ($this->files() as $file) {
            $extension = strtolower($file->getExtension());

            if ($this->extensions !== null && !in_array($extension, $this->extensions, true)) {
                continue;
            }

            $reader = static::$readers[$extension] ?? null;

            if ($reader === null) {
                continue;
            }

            array_push($documents, ...(new $reader($file->getPathname(), $this->metadata))->read());
        }

        return $documents;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    protected function files(): iterable
    {
        if (!$this->recursive) {
            return new FilesystemIterator($this->path, FilesystemIterator::SKIP_DOTS);
        }

        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
        );
    }
}
