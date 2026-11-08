<?php

namespace Ryra\Core\HttpClient;

use Ryra\Core\Container\ServiceProvider;

class HttpClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->transient('http.client', \Ryra\Core\HttpClient\Http::class);
        $this->app->alias('http.client', \Ryra\Core\Contracts\HttpClient\Http::class);
    }
}