<?php

namespace Ryra\Core\Mail\Mailables;

use Symfony\Component\Mime\Part\File;

class Attachment
{
    public ?string $name = null;

    public ?string $mine = null;

    protected function __construct(public mixed $resource) {}

    public static function fromPath(string $path): static
    {
        return new static(new File($path));
    }

    public static function fromStorage(string $path): static
    {
        return new static(new File(storage_path($path)));
    }

    public static function fromData(\Closure $data, string $name): static
    {
        $attachment = new static($data());
        $attachment->as($name);
        return $attachment;
    }

    public function as(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function withMime(string $mine): static
    {
        $this->mine = $mine;
        return $this;
    }
}