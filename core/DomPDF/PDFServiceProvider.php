<?php

namespace Ryra\Core\DomPDF;

use Ryra\Core\Container\ServiceProvider;
use Dompdf\Dompdf;

class PDFServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('dompdf.options', function ($app) {
            $defines = $app['config']->get('dompdf.defines');

            if ($defines) {
                $options = [];
                /**
                 * @var string $key
                 * @var mixed $value
                 */
                foreach ($defines as $key => $value) {
                    $key = strtolower(str_replace('DOMPDF_', '', $key));
                    $options[$key] = $value;
                }
            } else {
                $options = $app['config']->get('dompdf.options');
            }

            return $options;
        });

        $this->app->transient('dompdf', function ($app) {
            $options = $app->get('dompdf.options');
            $dompdf = new Dompdf($options);
            $path = realpath($app['config']->get('dompdf.public_path') ?: storage_path('public'));
            if ($path === false) {
                throw new \RuntimeException('Cannot resolve public path');
            }
            $dompdf->setBasePath($path);
            return $dompdf;
        });
        $this->app->alias('dompdf', Dompdf::class);

        $this->app->transient('dompdf.wrapper', fn ($app) => new PDF($app['dompdf'], $app['config']));
    }
}