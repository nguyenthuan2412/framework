<?php

namespace Ryra\Core\Excel\Files;

class TemporaryFile
{
    private string $path;

    public function __construct(string $extension = 'xlsx')
    {
        $base = tempnam(sys_get_temp_dir(), 'excel_');
        // tempnam creates a file without extension, rename with extension
        $this->path = $base . '.' . ltrim($extension, '.');
        rename($base, $this->path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getExtension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    public function readContents(): string
    {
        return file_get_contents($this->path) ?: '';
    }

    public function delete(): void
    {
        if (file_exists($this->path)) {
            @unlink($this->path);
        }
    }

    public function __destruct()
    {
        $this->delete();
    }
}
