<?php

namespace Ryra\Core\SEOTool\Meta;

class OpenGraph
{
    protected ?string $title = null;
    protected ?string $description = null;
    protected ?string $url = null;
    protected ?string $type = null;
    protected ?string $siteName = null;
    protected ?string $locale = null;
    protected array $alternateLocales = [];
    protected array $images = [];
    protected array $videos = [];
    protected array $properties = [];

    public function __construct(array $defaults = [])
    {
        $this->type = $defaults['type'] ?? 'website';
        $this->siteName = $defaults['site_name'] ?? null;
        $this->locale = $defaults['locale'] ?? null;
    }

    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function url(string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function siteName(string $siteName): static
    {
        $this->siteName = $siteName;
        return $this;
    }

    public function locale(string $locale): static
    {
        $this->locale = $locale;
        return $this;
    }

    public function alternateLocale(string $locale): static
    {
        $this->alternateLocales[] = $locale;
        return $this;
    }

    public function addImage(string $url, array $attributes = []): static
    {
        $this->images[] = array_merge(['url' => $url], $attributes);
        return $this;
    }

    public function addVideo(string $url, array $attributes = []): static
    {
        $this->videos[] = array_merge(['url' => $url], $attributes);
        return $this;
    }

    public function setProperty(string $property, string $value): static
    {
        $this->properties[$property] = $value;
        return $this;
    }

    public function article(
        ?string $author = null,
        ?string $publishedTime = null,
        ?string $modifiedTime = null,
        ?string $section = null,
        array $tags = []
    ): static {
        $this->type = 'article';

        if ($author !== null) {
            $this->properties['article:author'] = $author;
        }
        if ($publishedTime !== null) {
            $this->properties['article:published_time'] = $publishedTime;
        }
        if ($modifiedTime !== null) {
            $this->properties['article:modified_time'] = $modifiedTime;
        }
        if ($section !== null) {
            $this->properties['article:section'] = $section;
        }
        foreach ($tags as $tag) {
            $this->properties["article:tag:{$tag}"] = $tag;
        }

        return $this;
    }

    public function render(): string
    {
        $html = [];
        $e = fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');

        if ($this->type !== null) {
            $html[] = "<meta property=\"og:type\" content=\"{$e($this->type)}\">";
        }

        if ($this->title !== null) {
            $html[] = "<meta property=\"og:title\" content=\"{$e($this->title)}\">";
        }

        if ($this->description !== null) {
            $html[] = "<meta property=\"og:description\" content=\"{$e($this->description)}\">";
        }

        if ($this->url !== null) {
            $html[] = "<meta property=\"og:url\" content=\"{$e($this->url)}\">";
        }

        if ($this->siteName !== null) {
            $html[] = "<meta property=\"og:site_name\" content=\"{$e($this->siteName)}\">";
        }

        if ($this->locale !== null) {
            $html[] = "<meta property=\"og:locale\" content=\"{$e($this->locale)}\">";
        }

        foreach ($this->alternateLocales as $locale) {
            $html[] = "<meta property=\"og:locale:alternate\" content=\"{$e($locale)}\">";
        }

        foreach ($this->images as $image) {
            $html[] = "<meta property=\"og:image\" content=\"{$e($image['url'])}\">";
            if (isset($image['width'])) {
                $html[] = "<meta property=\"og:image:width\" content=\"{$e((string) $image['width'])}\">";
            }
            if (isset($image['height'])) {
                $html[] = "<meta property=\"og:image:height\" content=\"{$e((string) $image['height'])}\">";
            }
            if (isset($image['alt'])) {
                $html[] = "<meta property=\"og:image:alt\" content=\"{$e($image['alt'])}\">";
            }
            if (isset($image['type'])) {
                $html[] = "<meta property=\"og:image:type\" content=\"{$e($image['type'])}\">";
            }
        }

        foreach ($this->videos as $video) {
            $html[] = "<meta property=\"og:video\" content=\"{$e($video['url'])}\">";
            if (isset($video['width'])) {
                $html[] = "<meta property=\"og:video:width\" content=\"{$e((string) $video['width'])}\">";
            }
            if (isset($video['height'])) {
                $html[] = "<meta property=\"og:video:height\" content=\"{$e((string) $video['height'])}\">";
            }
            if (isset($video['type'])) {
                $html[] = "<meta property=\"og:video:type\" content=\"{$e($video['type'])}\">";
            }
        }

        foreach ($this->properties as $property => $value) {
            if (str_starts_with($property, 'article:tag:')) {
                $html[] = "<meta property=\"article:tag\" content=\"{$e($value)}\">";
            } else {
                $html[] = "<meta property=\"{$e($property)}\" content=\"{$e($value)}\">";
            }
        }

        return implode("\n", $html);
    }

    public function reset(): static
    {
        $this->title = null;
        $this->description = null;
        $this->url = null;
        $this->type = 'website';
        $this->alternateLocales = [];
        $this->images = [];
        $this->videos = [];
        $this->properties = [];
        return $this;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
