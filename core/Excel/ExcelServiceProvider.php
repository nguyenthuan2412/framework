<?php

namespace Ryra\Core\Excel;

use Ryra\Core\Container\ServiceProvider;
use Ryra\Core\Excel\Readers\ImportReader;
use Ryra\Core\Excel\Writers\ExportWriter;

class ExcelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('excel.writer', fn () => new ExportWriter());
        $this->app->singleton('excel.reader', fn () => new ImportReader());

        $this->app->singleton('excel', fn ($app) => new Excel(
            $app->get('excel.writer'),
            $app->get('excel.reader')
        ));

        $this->app->alias('excel', Excel::class);
    }
}
