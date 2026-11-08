<?php

namespace Ryra\Core\SEOTool\Sitemap;

class SitemapWriter
{
    protected int $countUrls = 0;

    protected int $currentFile = 0;

    /** @var resource|null */
    protected $pointerResource = null;

    protected ?string $baseUrl = null;

    public function __construct(
        protected string $file,
        protected int $maxUrls,
        protected bool $pretty = false
    ) {}

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function writeUrl(Url|array $url): void
    {
        if (is_array($url)) {
            $url = Url::createFromArray($url);
        }

        if ($this->maxUrls > 0 && $this->countUrls >= $this->maxUrls) {
            $this->closeFile();
            $this->countUrls = 0;
        }

        $buffer = '';
        if ($this->countUrls === 0) {
            $this->currentFile++;
            $this->createFile();
            $buffer = $this->xmlHeader();
        }

        $buffer .= $url->render($this->pretty);
        $this->write($buffer);
        $this->countUrls++;
    }

    protected function xmlHeader(): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset"
            . " xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\""
            . " xmlns:xhtml=\"http://www.w3.org/1999/xhtml\""
            . " xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\""
            . " xmlns:video=\"http://www.google.com/schemas/sitemap-video/1.1\""
            . " xmlns:news=\"http://www.google.com/schemas/sitemap-news/0.9\""
            . ">";
    }

    protected function createFile(): void
    {
        $filePath = $this->getIncrementedSitemapName($this->currentFile);
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->pointerResource = fopen($filePath, 'wb');
    }

    protected function getIncrementedSitemapName(int $currentFile): string
    {
        $pathInfo = pathinfo($this->file);
        $dir = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        $newFilename = "{$filename}-{$currentFile}{$ext}";
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newFilename;
    }

    protected function closeFile(): void
    {
        if ($this->pointerResource) {
            $this->write($this->pretty ? "\n</urlset>" : "</urlset>");
            fclose($this->pointerResource);
            $this->pointerResource = null;
        }
    }

    protected function write(string $buffer): void
    {
        fwrite($this->pointerResource, $buffer);
    }

    public function close(): void
    {
        $this->closeFile();

        if ($this->currentFile === 1) {
            $oldFileName = $this->getIncrementedSitemapName(1);
            rename($oldFileName, $this->file);
        } else {
            $index = new Index($this->file, $this->pretty);
            for ($i = 1; $i <= $this->currentFile; $i++) {
                $sitemapFile = $this->getIncrementedSitemapName($i);
                $loc = $this->baseUrl
                    ? $this->baseUrl . '/' . basename($sitemapFile)
                    : $sitemapFile;
                $index->writeSitemap($loc, time());
            }
            $index->close();
        }
    }

    public function __destruct()
    {
        if ($this->pointerResource !== null) {
            $this->closeFile();
        }
    }
}