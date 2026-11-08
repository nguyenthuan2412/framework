<?php

namespace Ryra\Core\SEOTool\Sitemap;

use DateTime;

class Video
{
    protected ?string $contentLoc = null;
    protected ?string $playerLoc = null;
    protected ?int $duration = null;
    protected ?DateTime $expirationDate = null;
    protected ?float $rating = null;
    protected ?int $viewCount = null;
    protected ?DateTime $publicationDate = null;
    protected ?bool $familyFriendly = null;
    protected ?bool $live = null;
    protected array $tags = [];

    protected function __construct(
        protected string $thumbnailLoc,
        protected string $title,
        protected string $description
    ) {}

    public static function create(string $thumbnailLoc, string $title, string $description): static
    {
        return new static($thumbnailLoc, $title, $description);
    }

    public function contentLoc(string $contentLoc): static
    {
        $this->contentLoc = $contentLoc;
        return $this;
    }

    public function playerLoc(string $playerLoc): static
    {
        $this->playerLoc = $playerLoc;
        return $this;
    }

    public function duration(int $seconds): static
    {
        $this->duration = $seconds;
        return $this;
    }

    public function expirationDate(DateTime $date): static
    {
        $this->expirationDate = $date;
        return $this;
    }

    public function rating(float $rating): static
    {
        $this->rating = max(0.0, min(5.0, $rating));
        return $this;
    }

    public function viewCount(int $count): static
    {
        $this->viewCount = $count;
        return $this;
    }

    public function publicationDate(DateTime $date): static
    {
        $this->publicationDate = $date;
        return $this;
    }

    public function familyFriendly(bool $value = true): static
    {
        $this->familyFriendly = $value;
        return $this;
    }

    public function live(bool $value = true): static
    {
        $this->live = $value;
        return $this;
    }

    public function tags(array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function render(bool $pretty = false): string
    {
        $e = fn(string $v) => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $segments = [
            "<video:thumbnail_loc>{$e($this->thumbnailLoc)}</video:thumbnail_loc>",
            "<video:title>{$e($this->title)}</video:title>",
            "<video:description>{$e($this->description)}</video:description>",
        ];

        if ($this->contentLoc !== null) {
            $segments[] = "<video:content_loc>{$e($this->contentLoc)}</video:content_loc>";
        }
        if ($this->playerLoc !== null) {
            $segments[] = "<video:player_loc>{$e($this->playerLoc)}</video:player_loc>";
        }
        if ($this->duration !== null) {
            $segments[] = "<video:duration>{$this->duration}</video:duration>";
        }
        if ($this->expirationDate !== null) {
            $segments[] = "<video:expiration_date>{$this->expirationDate->format('c')}</video:expiration_date>";
        }
        if ($this->rating !== null) {
            $segments[] = "<video:rating>{$this->rating}</video:rating>";
        }
        if ($this->viewCount !== null) {
            $segments[] = "<video:view_count>{$this->viewCount}</video:view_count>";
        }
        if ($this->publicationDate !== null) {
            $segments[] = "<video:publication_date>{$this->publicationDate->format('c')}</video:publication_date>";
        }
        if ($this->familyFriendly !== null) {
            $val = $this->familyFriendly ? 'yes' : 'no';
            $segments[] = "<video:family_friendly>{$val}</video:family_friendly>";
        }
        if ($this->live !== null) {
            $val = $this->live ? 'yes' : 'no';
            $segments[] = "<video:live>{$val}</video:live>";
        }
        foreach ($this->tags as $tag) {
            $segments[] = "<video:tag>{$e($tag)}</video:tag>";
        }

        if ($pretty) {
            $inner = implode("\n\t\t\t", $segments);
            return "\n\t\t<video:video>\n\t\t\t{$inner}\n\t\t</video:video>";
        }

        $inner = implode('', $segments);
        return "<video:video>{$inner}</video:video>";
    }
}
