<?php

namespace Ryra\Core\SEOTool\Sitemap;

use DateTime;

class Url
{
    protected array $languages = [];

    protected float $priority = 0.5;

    protected DateTime|int|null $lastModificationDate = null;

    protected ?Frequency $frequency = null;

    /** @var Image[] */
    protected array $images = [];

    /** @var Video[] */
    protected array $videos = [];

    protected ?News $news = null;

    protected function __construct(protected string $loc) {}

    public static function create(string $loc): static
    {
        return new static($loc);
    }

    public static function createFromArray(array $array): static
    {
        $url = new static($array['loc']);
        if (isset($array['changefreq'])) {
            $url->frequency(
                $array['changefreq'] instanceof Frequency
                    ? $array['changefreq']
                    : Frequency::from($array['changefreq'])
            );
        }
        if (isset($array['priority'])) {
            $url->priority($array['priority']);
        }
        if (isset($array['lastmod'])) {
            $url->lastModificationDate($array['lastmod']);
        }
        if (isset($array['languages'])) {
            $url->languages($array['languages']);
        }
        return $url;
    }

    public function languages(iterable $languages): static
    {
        foreach ($languages as $langId => $link) {
            $this->languages[$langId] = $link;
        }
        return $this;
    }

    public function priority(float $priority): static
    {
        $this->priority = max(0.0, min(1.0, $priority));
        return $this;
    }

    public function lastModificationDate(DateTime|int $lastModificationDate): static
    {
        $this->lastModificationDate = $lastModificationDate;
        return $this;
    }

    public function frequency(Frequency $frequency): static
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function addImage(Image $image): static
    {
        $this->images[] = $image;
        return $this;
    }

    public function addVideo(Video $video): static
    {
        $this->videos[] = $video;
        return $this;
    }

    public function news(News $news): static
    {
        $this->news = $news;
        return $this;
    }

    public function render(bool $pretty = false): string
    {
        $loc = htmlspecialchars($this->loc, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $segments = [
            "<loc>{$loc}</loc>"
        ];

        if (is_int($this->lastModificationDate)) {
            $date = date('c', $this->lastModificationDate);
            $segments[] = "<lastmod>{$date}</lastmod>";
        } elseif ($this->lastModificationDate instanceof DateTime) {
            $date = $this->lastModificationDate->format('c');
            $segments[] = "<lastmod>{$date}</lastmod>";
        }

        if ($this->frequency !== null) {
            $segments[] = "<changefreq>{$this->frequency->value}</changefreq>";
        }

        $segments[] = "<priority>{$this->priority}</priority>";

        $separator = '';
        $indent = '';
        if ($pretty) {
            $separator = "\n\t\t";
            $indent = "\n\t";
        }

        if (!empty($this->languages)) {
            foreach ($this->languages as $langId => $link) {
                $link = htmlspecialchars($link, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $segments[] = "<xhtml:link rel=\"alternate\" hreflang=\"{$langId}\" href=\"{$link}\" />";
            }
        }

        foreach ($this->images as $image) {
            $segments[] = $image->render($pretty);
        }

        foreach ($this->videos as $video) {
            $segments[] = $video->render($pretty);
        }

        if ($this->news !== null) {
            $segments[] = $this->news->render($pretty);
        }

        $content = implode($separator, $segments);
        return "{$indent}<url>{$separator}{$content}{$indent}</url>";
    }
}