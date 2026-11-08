<?php

namespace Ryra\Core\SEOTool\Sitemap;

class Sitemap
{
    protected SitemapWriter $writer;

    /**
     * @var \Ryra\Core\SEOTool\Sitemap\Url[]
     */
    protected array $staticUrls = [];

    /**
     * @var class-string<\Ryra\Core\Database\Model\Model>[];
     */
    protected array $models = [];

    public function __construct(
        string $file,
        int $maxUrls,
        bool $pretty = false
    ) {
        $this->writer = new SitemapWriter($file, $maxUrls, $pretty);
    }

    public function addStatic(Url $url): static
    {
        $this->staticUrls[] = $url;
        return $this;
    }

    /**
     * @var class-string<\Ryra\Core\SEOTool\Sitemap\Sitemapable>;
     */
    public function addDynamic(string $model): static
    {
        $this->models[] = $model;
        return $this;
    }

    public function write(): void
    {
        foreach ($this->staticUrls as $url) {
            $this->writer->writeUrl($url);
        }

        foreach ($this->models as $class) {
            $query = (new $class())->query();
            $class::sitemapQuery($query);

            /**
             * @var \Ryra\Core\SEOTool\Sitemap\Sitemapable[];
             */
            $models = $query->cursor();
            foreach ($models as $model) {
                $this->writer->writeUrl($model->toSitemapTag());
            }
        }
        $this->writer->close();
    }
}