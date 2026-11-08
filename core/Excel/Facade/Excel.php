<?php

namespace Ryra\Core\Excel\Facade;

use Ryra\Core\Excel\Files\TemporaryFile;
use Ryra\Core\Http\Response;
use Ryra\Core\Support\Facade;

/**
 * @method static Response      download(object $exportable, string $fileName, string $writerType = 'Xlsx', array $headers = [])
 * @method static bool          store(object $exportable, string $filePath, string|null $disk = null, string $writerType = 'Xlsx')
 * @method static string        raw(object $exportable, string $writerType = 'Xlsx')
 * @method static TemporaryFile toTemporaryFile(object $exportable, string $writerType = 'Xlsx')
 * @method static void          import(object $importable, string $filePath, string|null $disk = null, string|null $readerType = null)
 * @method static array         toArray(object $importable, string $filePath, string|null $disk = null, string|null $readerType = null)
 * @method static array         toCollection(object $importable, string $filePath, string|null $disk = null, string|null $readerType = null)
 *
 * @see BaseExcel
 */
class Excel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'excel';
    }
}
