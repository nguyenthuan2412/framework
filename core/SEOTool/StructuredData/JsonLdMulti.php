<?php

namespace Ryra\Core\SEOTool\StructuredData;

class JsonLdMulti
{
    /** @var JsonLd[] */
    protected array $items = [];

    public function add(JsonLd $jsonLd): static
    {
        $this->items[] = $jsonLd;
        return $this;
    }

    public function create(): JsonLd
    {
        $jsonLd = new JsonLd();
        $this->items[] = $jsonLd;
        return $jsonLd;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function toArray(): array
    {
        return array_map(fn(JsonLd $item) => $item->toArray(), $this->items);
    }

    public function render(): string
    {
        if (empty($this->items)) {
            return '';
        }

        if (count($this->items) === 1) {
            return $this->items[0]->render();
        }

        $graphs = array_map(fn(JsonLd $item) => $item->toArray(), $this->items);
        $data = [
            '@context' => 'https://schema.org',
            '@graph' => $graphs,
        ];

        // Remove duplicate @context from graph items
        foreach ($data['@graph'] as &$item) {
            unset($item['@context']);
        }

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return "<script type=\"application/ld+json\">\n{$json}\n</script>";
    }

    public function reset(): static
    {
        $this->items = [];
        return $this;
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
