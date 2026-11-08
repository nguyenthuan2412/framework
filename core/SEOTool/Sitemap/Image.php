<?php

namespace Ryra\Core\SEOTool\Sitemap;

class Image
{
    protected ?string $caption = null;
    protected ?string $geoLocation = null;
    protected ?string $title = null;
    protected ?string $license = null;

    protected function __construct(protected string $loc) {}

    public static function create(string $loc): static
    {
        return new static($loc);
    }

    public function caption(string $caption): static
    {
        $this->caption = $caption;
        return $this;
    }

    public function geoLocation(string $geoLocation): static
    {
        $this->geoLocation = $geoLocation;
        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function license(string $license): static
    {
        $this->license = $license;
        return $this;
    }

    public function render(bool $pretty = false): string
    {
        $e = fn(string $v) => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $segments = [
            "<image:loc>{$e($this->loc)}</image:loc>",
        ];

        if ($this->caption !== null) {
            $segments[] = "<image:caption>{$e($this->caption)}</image:caption>";
        }
        if ($this->geoLocation !== null) {
            $segments[] = "<image:geo_location>{$e($this->geoLocation)}</image:geo_location>";
        }
        if ($this->title !== null) {
            $segments[] = "<image:title>{$e($this->title)}</image:title>";
        }
        if ($this->license !== null) {
            $segments[] = "<image:license>{$e($this->license)}</image:license>";
        }

        if ($pretty) {
            $inner = implode("\n\t\t\t", $segments);
            return "\n\t\t<image:image>\n\t\t\t{$inner}\n\t\t</image:image>";
        }

        $inner = implode('', $segments);
        return "<image:image>{$inner}</image:image>";
    }
}