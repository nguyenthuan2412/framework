<?php

namespace Ryra\Core\SEOTool\Sitemap;

use Ryra\Core\Database\Model\Builder;

interface Sitemapable
{
    public function toSitemapTag(): Url;

    public static function sitemapQuery(Builder $builder): void;
}