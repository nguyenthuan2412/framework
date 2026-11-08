<?php

namespace Ryra\Core\SEOTool\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static \Ryra\Core\SEOTool\Sitemap addStatic(\Ryra\Core\SEOTool\Sitemap\Url $url)
 * @method static \Ryra\Core\SEOTool\Sitemap addDynamic(string $model)
 * @method static void write()
 */
class Sitemap extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sitemap';
    }
}
