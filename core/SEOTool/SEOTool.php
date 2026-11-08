<?php

namespace Ryra\Core\SEOTool;

use Ryra\Core\SEOTool\Meta\SEOMeta;
use Ryra\Core\SEOTool\Meta\OpenGraph;
use Ryra\Core\SEOTool\Meta\TwitterCards;
use Ryra\Core\SEOTool\StructuredData\JsonLdMulti;

class SEOTool
{
    public function __construct(
        protected SEOMeta $meta,
        protected OpenGraph $openGraph,
        protected TwitterCards $twitter,
        protected JsonLdMulti $jsonLd
    ) {}

    public function meta(): SEOMeta
    {
        return $this->meta;
    }

    public function openGraph(): OpenGraph
    {
        return $this->openGraph;
    }

    public function twitter(): TwitterCards
    {
        return $this->twitter;
    }

    public function jsonLd(): JsonLdMulti
    {
        return $this->jsonLd;
    }

    public function title(string $title): static
    {
        $this->meta->title($title);
        $this->openGraph->title($title);
        $this->twitter->title($title);
        return $this;
    }

    public function description(string $description): static
    {
        $this->meta->description($description);
        $this->openGraph->description($description);
        $this->twitter->description($description);
        return $this;
    }

    public function canonical(string $url): static
    {
        $this->meta->canonical($url);
        $this->openGraph->url($url);
        return $this;
    }

    public function image(string $url, array $attributes = []): static
    {
        $this->openGraph->addImage($url, $attributes);
        $this->twitter->image($url, $attributes['alt'] ?? null);
        return $this;
    }

    public function render(): string
    {
        $parts = [
            $this->meta->render(),
            $this->openGraph->render(),
            $this->twitter->render(),
        ];

        if (!$this->jsonLd->isEmpty()) {
            $parts[] = $this->jsonLd->render();
        }

        return implode("\n", array_filter($parts, fn(string $v) => $v !== ''));
    }

    public function reset(): static
    {
        $this->meta->reset();
        $this->openGraph->reset();
        $this->twitter->reset();
        $this->jsonLd->reset();
        return $this;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
