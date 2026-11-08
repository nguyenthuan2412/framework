<?php

namespace Ryra\Core\SEOTool\StructuredData;

class JsonLd
{
    protected ?string $type = null;
    protected array $data = [];

    public function __construct(array $defaults = [])
    {
        if (!empty($defaults)) {
            $this->data = $defaults;
        }
    }

    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function setData(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function toArray(): array
    {
        $result = ['@context' => 'https://schema.org'];

        if ($this->type !== null) {
            $result['@type'] = $this->type;
        }

        return array_merge($result, $this->data);
    }

    public function render(): string
    {
        $json = json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return "<script type=\"application/ld+json\">\n{$json}\n</script>";
    }

    public function reset(): static
    {
        $this->type = null;
        $this->data = [];
        return $this;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public static function website(string $name, string $url): static
    {
        return (new static())
            ->type('WebSite')
            ->set('name', $name)
            ->set('url', $url);
    }

    public static function organization(string $name, string $url, ?string $logo = null): static
    {
        $ld = (new static())
            ->type('Organization')
            ->set('name', $name)
            ->set('url', $url);

        if ($logo !== null) {
            $ld->set('logo', $logo);
        }

        return $ld;
    }

    public static function breadcrumbs(array $items): static
    {
        $list = [];
        foreach ($items as $i => $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
            ];
            if (isset($item['url'])) {
                $entry['item'] = $item['url'];
            }
            $list[] = $entry;
        }

        return (new static())
            ->type('BreadcrumbList')
            ->set('itemListElement', $list);
    }

    public static function article(
        string $headline,
        string $datePublished,
        ?string $dateModified = null,
        ?string $author = null,
        ?string $image = null
    ): static {
        $ld = (new static())
            ->type('Article')
            ->set('headline', $headline)
            ->set('datePublished', $datePublished);

        if ($dateModified !== null) {
            $ld->set('dateModified', $dateModified);
        }
        if ($author !== null) {
            $ld->set('author', ['@type' => 'Person', 'name' => $author]);
        }
        if ($image !== null) {
            $ld->set('image', $image);
        }

        return $ld;
    }

    public static function faq(array $questions): static
    {
        $entities = [];
        foreach ($questions as $question => $answer) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        return (new static())
            ->type('FAQPage')
            ->set('mainEntity', $entities);
    }
}
