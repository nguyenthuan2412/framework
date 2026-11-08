<?php

namespace Ryra\Core\ValidatedDTO\Rules;

use Attribute;
use Ryra\Core\ValidatedDTO\MimeTypeGuesser;
use Ryra\Core\ValidatedDTO\Property;
use Ryra\Core\ValidatedDTO\Rule;
use Ryra\Core\ValidatedDTO\Traits\FileTrait;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsImage extends Rule
{
    use FileTrait;

    protected static array $allowTypes = [];

    public function __construct(protected string $message = 'The :attribute must be image.') {
        if (empty(static::$allowTypes)) {
            static::$allowTypes = [
                MimeTypeGuesser::getMimeType('jpeg'),
                MimeTypeGuesser::getMimeType('png'),
                MimeTypeGuesser::getMimeType('bmp'),
                MimeTypeGuesser::getMimeType('gif'),
                MimeTypeGuesser::getMimeType('svg'),
                MimeTypeGuesser::getMimeType('webp')
            ];
        }
    }

    public function check(mixed &$value, array &$values, Property $property): bool
    {
        if (\is_string($value)) {
            return \file_exists($value) && \in_array(\mime_content_type($value), static::$allowTypes);
        }

        if ($this->isUploadedFile($value)) {
            return \in_array($value->getClientMimeType(), static::$allowTypes);
        }

        return false;
    }
}