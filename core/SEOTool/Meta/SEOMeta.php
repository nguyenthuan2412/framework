<?php

namespace Ryra\Core\SEOTool\Meta;

class SEOMeta
{
    protected ?string $title = null;
    protected ?string $titleSeparator = null;
    protected ?string $titleSuffix = null;
    protected ?string $description = null;
    protected array $keywords = [];
    protected ?string $canonical = null;
    protected ?string $prev = null;
    protected ?string $next = null;
    protected array $robots = [];
    protected array $meta = [];

    public function __construct(array $defaults = [])
    {
        $this->titleSeparator = $defaults['separator'] ?? ' | ';
        $this->titleSuffix = $defaults['suffix'] ?? null;

        if (isset($defaults['description'])) {
            $this->description($defaults['description']);
        }
        if (isset($defaults['keywords'])) {
            $this->keywords($defaults['keywords']);
        }
        if (isset($defaults['robots'])) {
            $this->robots = (array) $defaults['robots'];
        }
    }

    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function titleSuffix(string $suffix): static
    {
        $this->titleSuffix = $suffix;
        return $this;
    }

    public function titleSeparator(string $separator): static
    {
        $this->titleSeparator = $separator;
        return $this;
    }

    public function getTitle(): string
    {
        if ($this->title === null) {
            return $this->titleSuffix ?? '';
        }

        if ($this->titleSuffix !== null) {
            return $this->title . $this->titleSeparator . $this->titleSuffix;
        }

        return $this->title;
    }

    public function description(string $description): static
    {
        $this->description = mb_substr($description, 0, 160);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function keywords(string|array $keywords): static
    {
        $this->keywords = is_array($keywords) ? $keywords : explode(',', $keywords);
        $this->keywords = array_map('trim', $this->keywords);
        return $this;
    }

    public function addKeyword(string $keyword): static
    {
        $this->keywords[] = trim($keyword);
        return $this;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function canonical(string $url): static
    {
        $this->canonical = $url;
        return $this;
    }

    public function getCanonical(): ?string
    {
        return $this->canonical;
    }

    public function prev(string $url): static
    {
        $this->prev = $url;
        return $this;
    }

    public function next(string $url): static
    {
        $this->next = $url;
        return $this;
    }

    public function robots(string ...$directives): static
    {
        $this->robots = $directives;
        return $this;
    }

    public function noIndex(): static
    {
        if (!in_array('noindex', $this->robots)) {
            $this->robots[] = 'noindex';
        }
        return $this;
    }

    public function noFollow(): static
    {
        if (!in_array('nofollow', $this->robots)) {
            $this->robots[] = 'nofollow';
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

        $title = $this->getTitle();
        if ($title !== '') {
            $html[] = "<title>{$e($title)}</title>";
        }

        if ($this->description !== null) {
            $html[] = "<meta name=\"description\" content=\"{$e($this->description)}\">";
        }

        if (!empty($this->keywords)) {
            $html[] = "<meta name=\"keywords\" content=\"{$e(implode(', ', $this->keywords))}\">";
        }

        if (!empty($this->robots)) {
            $html[] = "<meta name=\"robots\" content=\"{$e(implode(', ', $this->robots))}\">";
        }

        if ($this->canonical !== null) {
            $html[] = "<link rel=\"canonical\" href=\"{$e($this->canonical)}\">";
        }

        if ($this->prev !== null) {
            $html[] = "<link rel=\"prev\" href=\"{$e($this->prev)}\">";
        }

        if ($this->next !== null) {
            $html[] = "<link rel=\"next\" href=\"{$e($this->next)}\">";
        }

        foreach ($this->meta as $name => $content) {
            $html[] = "<meta name=\"{$e($name)}\" content=\"{$e($content)}\">";
        }

        return implode("\n", $html);
    }

    public function reset(): static
    {
        $this->title = null;
        $this->description = null;
        $this->keywords = [];
        $this->canonical = null;
        $this->prev = null;
        $this->next = null;
        $this->robots = [];
        $this->meta = [];
        return $this;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
