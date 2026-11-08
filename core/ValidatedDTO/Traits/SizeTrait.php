<?php

namespace Ryra\Core\ValidatedDTO\Traits;

use InvalidArgumentException;

trait SizeTrait
{
    protected function getKilobytesSize(string|int $size): float
    {
        if (is_numeric($size)) {
            return (float) $size / 1024; // Vì mặc định là byte → KB
        }

        if (!is_string($size)) {
            throw new InvalidArgumentException('Size must be string or numeric Bytes', 1);
        }

        if (!preg_match("/^(?<number>((\d+)?\.)?\d+)(?<format>(B|K|M|G|T|P)B?)?$/i", $size, $match)) {
            throw new InvalidArgumentException('Size is not valid format', 1);
        }

        $number = (float) $match['number'];
        $format = strtoupper($match['format'] ?? '');

        switch ($format) {
            case 'B':
            case '':
                return $number / 1024;

            case 'KB':
            case 'K':
                return $number;

            case 'MB':
            case 'M':
                return $number * 1024;

            case 'GB':
            case 'G':
                return $number * pow(1024, 2);

            case 'TB':
            case 'T':
                return $number * pow(1024, 3);

            case 'PB':
            case 'P':
                return $number * pow(1024, 4);

            default:
                throw new InvalidArgumentException('Unknown size format');
        }
    }


    /**
     * Check whether value is from $_FILES
     */
    public function isUploadedFileValue(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        $keys = ['name', 'type', 'tmp_name', 'size', 'error'];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $value)) {
                return false;
            }
        }

        return true;
    }
}