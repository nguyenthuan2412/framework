<?php

namespace Ryra\Core\SEOTool;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\SEOTool\Sitemap\Sitemap;
use Ryra\Core\SEOTool\Sitemap\Url;

class SEOToolServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('sitemap', fn() => new Sitemap(
            (string) config('sitemap.dir_name'),
            (int) config('sitemap.max_urls', 0),
            (bool) config('sitemap.pretty', false)
        ));
    }

    public function boot(): void
    {
        $sitemap = $this->app->get('sitemap');
        foreach (config('sitemap.urls', []) as $url) {
            $sitemap->addStatic(Url::createFromArray($url));
        }
        
        foreach(config('sitemap.dynamic', []) as $model) {
            $sitemap->addDynamic($model);
        }
    }
}