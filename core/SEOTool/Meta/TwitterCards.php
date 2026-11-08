<?php

namespace Ryra\Core\SEOTool\Meta;

class TwitterCards
{
    protected ?string $card = null;
    protected ?string $site = null;
    protected ?string $creator = null;
    protected ?string $title = null;
    protected ?string $description = null;
    protected ?string $image = null;
    protected ?string $imageAlt = null;
    protected array $meta = [];

    public function __construct(array $defaults = [])
    {
        $this->card = $defaults['card'] ?? 'summary';
        $this->site = $defaults['site'] ?? null;
    }

    public function card(string $card): static
    {
        $this->card = $card;
        return $this;
    }

    public function summaryLargeImage(): static
    {
        $this->card = 'summary_large_image';
        return $this;
    }

    public function site(string $site): static
    {
        $this->site = $site;
        return $this;
    }

    public function creator(string $creator): static
    {
        $this->creator = $creator;
        return $this;
    }

    public function title(string $title): static
    {
        $this->title = mb_substr($title, 0, 70);
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function description(string $description): static
    {
        $this->description = mb_substr($description, 0, 200);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function image(string $url, ?string $alt = null): static
    {
        $this->image = $url;
        if ($alt !== null) {
            $this->imageAlt = $alt;
        }
        return $this;
    }

    public function addMeta(string $name, string $content): static
    {
        $this->meta[$name] = $content;
        return $this;
    }

    public function render(): string
    {
        $html = [];
        $e = fn(?string $v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');

        if ($this->card !== null) {
            $html[] = "<meta name=\"twitter:card\" content=\"{$e($this->card)}\">";
        }

        if ($this->site !== null) {
            $html[] = "<meta name=\"twitter:site\" content=\"{$e($this->site)}\">";
        }

        if ($this->creator !== null) {
            $html[] = "<meta name=\"twitter:creator\" content=\"{$e($this->creator)}\">";
        }

        if ($this->title !== null) {
            $html[] = "<meta name=\"twitter:title\" content=\"{$e($this->title)}\">";
        }

        if ($this->description !== null) {
            $html[] = "<meta name=\"twitter:description\" content=\"{$e($this->description)}\">";
        }

        if ($this->image !== null) {
            $html[] = "<meta name=\"twitter:image\" content=\"{$e($this->image)}\">";
            if ($this->imageAlt !== null) {
                $html[] = "<meta name=\"twitter:image:alt\" content=\"{$e($this->imageAlt)}\">";
            }
        }

        foreach ($this->meta as $name => $content) {
            $html[] = "<meta name=\"twitter:{$e($name)}\" content=\"{$e($content)}\">";
        }

        return implode("\n", $html);
    }

    public function reset(): static
    {
        $this->title = null;
        $this->description = null;
        $this->creator = null;
        $this->image = null;
        $this->imageAlt = null;
        $this->meta = [];
        return $this;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
