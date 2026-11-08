<?php

namespace Ryra\Core\SEOTool\Sitemap;

use DateTime;

class News
{
    protected ?string $access = null;
    protected array $genres = [];
    protected array $keywords = [];
    protected array $stockTickers = [];

    protected function __construct(
        protected string $publicationName,
        protected string $publicationLanguage,
        protected DateTime|int $publicationDate,
        protected string $title
    ) {}

    public static function create(
        string $publicationName,
        string $publicationLanguage,
        DateTime|int $publicationDate,
        string $title
    ): static {
        return new static($publicationName, $publicationLanguage, $publicationDate, $title);
    }

    public function access(string $access): static
    {
        $this->access = $access;
        return $this;
    }

    public function genres(array $genres): static
    {
        $this->genres = $genres;
        return $this;
    }

    public function keywords(array $keywords): static
    {
        $this->keywords = $keywords;
        return $this;
    }

    public function stockTickers(array $tickers): static
    {
        $this->stockTickers = $tickers;
        return $this;
    }

    public function render(bool $pretty = false): string
    {
        $e = fn(string $v) => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $date = is_int($this->publicationDate)
            ? date('c', $this->publicationDate)
            : $this->publicationDate->format('c');

        $segments = [
            "<news:publication><news:name>{$e($this->publicationName)}</news:name><news:language>{$e($this->publicationLanguage)}</news:language></news:publication>",
            "<news:publication_date>{$date}</news:publication_date>",
            "<news:title>{$e($this->title)}</news:title>",
        ];

        if ($this->access !== null) {
            $segments[] = "<news:access>{$e($this->access)}</news:access>";
        }
        if (!empty($this->genres)) {
            $segments[] = "<news:genres>{$e(implode(', ', $this->genres))}</news:genres>";
        }
        if (!empty($this->keywords)) {
            $segments[] = "<news:keywords>{$e(implode(', ', $this->keywords))}</news:keywords>";
        }
        if (!empty($this->stockTickers)) {
            $segments[] = "<news:stock_tickers>{$e(implode(', ', $this->stockTickers))}</news:stock_tickers>";
        }

        if ($pretty) {
            $inner = implode("\n\t\t\t", $segments);
            return "\n\t\t<news:news>\n\t\t\t{$inner}\n\t\t</news:news>";
        }

        $inner = implode('', $segments);
        return "<news:news>{$inner}</news:news>";
    }
}
