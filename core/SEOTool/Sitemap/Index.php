<?php

namespace Ryra\Core\SEOTool\Sitemap;

use DateTime;

class Index
{
    /** @var resource|null */
    protected $pointerResource = null;

    protected bool $headerWritten = false;

    public function __construct(
        protected string $file,
        protected bool $pretty = false
    ) {
        $this->pointerResource = fopen($file, 'wb');
    }

    public function writeSitemap(string $loc, DateTime|int|null $lastmod = null): void
    {
        if (!$this->headerWritten) {
            $this->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
            $this->write("<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">");
            $this->headerWritten = true;
        }

        $loc = htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        if ($this->pretty) {
            $this->write("\n\t<sitemap>");
            $this->write("\n\t\t<loc>{$loc}</loc>");
            if ($lastmod !== null) {
                $date = is_int($lastmod) ? date('c', $lastmod) : $lastmod->format('c');
                $this->write("\n\t\t<lastmod>{$date}</lastmod>");
            }
            $this->write("\n\t</sitemap>");
        } else {
            $buffer = "<sitemap><loc>{$loc}</loc>";
            if ($lastmod !== null) {
                $date = is_int($lastmod) ? date('c', $lastmod) : $lastmod->format('c');
                $buffer .= "<lastmod>{$date}</lastmod>";
            }
            $buffer .= "</sitemap>";
            $this->write($buffer);
        }
    }

    public function close(): void
    {
        if ($this->pointerResource) {
            if ($this->headerWritten) {
                $this->write($this->pretty ? "\n</sitemapindex>" : "</sitemapindex>");
            }
            fclose($this->pointerResource);
            $this->pointerResource = null;
        }
    }

    protected function write(string $buffer): void
    {
        fwrite($this->pointerResource, $buffer);
    }

    public function __destruct()
    {
        $this->close();
    }
}