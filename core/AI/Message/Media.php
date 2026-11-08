<?php

namespace Ryra\Core\AI\Message;

use Ryra\Core\AI\Exceptions\AIException;
use Ryra\Core\Support\Arrayable;

class Media implements Arrayable
{
    public function __construct(
        public readonly string $mimeType,
        public readonly string $data,
    ) {}

    public static function fromPath(string $path): static
    {
        if (!is_file($path)) {
            throw new AIException(sprintf('Media file not found: "%s".', $path));
        }

        $mimeType = mime_content_type($path);
        if ($mimeType === false) {
            throw new AIException(sprintf('Unable to detect mime type of "%s".', $path));
        }

        return new static($mimeType, base64_encode((string) file_get_contents($path)));
    }

    public static function fromBase64(string $mimeType, string $data): static
    {
        return new static($mimeType, $data);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function toArray(): array
    {
        return [
            'mime_type' => $this->mimeType,
            'data' => $this->data,
        ];
    }
}
