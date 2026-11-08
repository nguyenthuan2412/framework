<?php

namespace Ryra\Core\Image;

use Ryra\Core\App;
use Ryra\Core\Pipeline\Pipeline;
use Ryra\Core\Image\Modifiers\ToWebp;

class Image
{
    protected $image;

    public function __construct(protected string $filePath, protected $imageFactory) {}

    public function buildImage(string $style): string|false
    {
        $modifiers = $this->imageFactory->getModifiers($style);
        $uriStyleImage = $this->getUriImageStyle($style, $modifiers);
        $pathStyleImage = join_paths(storage_path(), $uriStyleImage);
        if (file_exists($pathStyleImage)) {
            return DS . $uriStyleImage;
        }

        foreach ($modifiers as $class => &$paramaters) {
            $paramaters = App::make($class, $paramaters);
        }
        unset($paramaters);
        $this->image = $this->imageFactory->manager->read($this->filePath);
        $image = (new Pipeline)
            ->send($this)
            ->through(array_values($modifiers))
            ->thenReturn();

        if ($this->saveImage($image, $pathStyleImage)) {
            return DS . $uriStyleImage;
        }

        return false;
    }

    public function toWebp(int $quality = 75, null|bool $strip = null): static
    {
        $this->image = $this->image->toWebp($quality, $strip);
        return $this;
    }

    protected function saveImage(Image $image, string $filePath): bool
    {
        $dirFile = dirname($filePath);
        if (!is_dir($dirFile)) {
            $lockFile = '/tmp/' . uniqid() . '.lock';
            $fp = fopen($lockFile, 'w+');
            if (flock($fp, LOCK_EX)) {
                if (!is_dir($dirFile)) {
                    mkdir($dirFile, 0755, true);
                }
                flock($fp, LOCK_UN);
            }
            fclose($fp);
            unlink($lockFile);
        }

        $image->save($filePath);
        return file_exists($filePath);
    }

    protected function getUriImageStyle(string &$style, array &$modifiers): string
    {
        $pathOriginal = $this->filePath;
        $pathOriginal = match (true) {
            isset($modifiers[ToWebp::class]) => $this->replaceExtension($pathOriginal, 'webp'),
            default => $pathOriginal
        };

        $publicPath = storage_path('public');
        return join_paths(
            'public',
            '.images',
            $style,
            ltrim(str_replace($publicPath, '', $pathOriginal), '/')
        );
    }

    protected function replaceExtension(string $filename, string $newExt): string
    {
        $info = pathinfo($filename);
        return
            ($info['dirname'] !== '' ? $info['dirname'] . DIRECTORY_SEPARATOR : '') .
            $info['filename'] . '.' . $newExt;
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->image->{$name}(...$arguments);
    }
}