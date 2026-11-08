<?php

namespace Ryra\Core\Filesystem\Facade;

use Ryra\Core\Support\Facade;

/**
 * @method static bool fileExists(string $location)
 * @method static bool directoryExists(string $location)
 * @method static bool has(string $location)
 * @method static \Ryra\Core\Filesystem\FilesystemInterface disk(?string $driver = null)
 * @method static void write(string $location, string $contents, array $config = [])
 * @method static void writeStream(string $location, $contents, array $config = [])
 * @method static string read(string $location)
 * @method static mixed readStream(string $location)
 * @method static void delete(string $location)
 * @method static void deleteDirectory(string $location)
 * @method static void createDirectory(string $location, array $config = [])
 * @method static void move(string $source, string $destination, array $config = [])
 * @method static void copy(string $source, string $destination, array $config = [])
 * @method static int lastModified(string $path)
 * @method static int fileSize(string $path)
 * @method static string mimeType(string $path)
 * @method static void setVisibility(string $path, string $visibility)
 * @method static string visibility(string $path)
 * @method static string publicUrl(string $path, array $config = [])
 * @method static string temporaryUrl(string $path, \DateTimeInterface $expiresAt, array $config = [])
 * @method static string checksum(string $path, array $config = [])
 * @method static string path(?string $path = null)
 */
class Filesystem extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'filesystem';
    }
}