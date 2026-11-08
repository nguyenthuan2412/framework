<?php

namespace Ryra\Core\SEOTool\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta title(string $title)
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta titleSuffix(string $suffix)
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta titleSeparator(string $separator)
 * @method static string getTitle()
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta description(string $description)
 * @method static ?string getDescription()
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta keywords(string|array $keywords)
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta addKeyword(string $keyword)
 * @method static array getKeywords()
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta canonical(string $url)
 * @method static ?string getCanonical()
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta prev(string $url)
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta next(string $url)
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta robots(string ...$directives)
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta noIndex()
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta noFollow()
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta addMeta(string $name, string $content)
 * @method static string render()
 * @method static \Ryra\Core\SEOTool\Meta\SEOMeta reset()
 *
 * @see \Ryra\Core\SEOTool\Meta\SEOMeta
 */
class SEOMeta extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'seo.meta';
    }
}
