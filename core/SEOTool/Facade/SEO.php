<?php

namespace Ryra\Core\SEOTool\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta meta()
 * @method static \Ryra\Core\SEOTool\Meta\OpenGraph openGraph()
 * @method static \Ryra\Core\SEOTool\Meta\TwitterCards twitter()
 * @method static \Ryra\Core\SEOTool\StructuredData\JsonLdMulti jsonLd()
 * @method static \Ryra\Core\SEOTool\SEOTool title(string $title)
 * @method static \Ryra\Core\SEOTool\SEOTool description(string $description)
 * @method static \Ryra\Core\SEOTool\SEOTool canonical(string $url)
 * @method static \Ryra\Core\SEOTool\SEOTool image(string $url, array $attributes = [])
 * @method static string render()
 * @method static \Ryra\Core\SEOTool\SEOTool reset()
 *
 * @see \Ryra\Core\SEOTool\SEOTool
 */
class SEO extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'seo';
    }
}
