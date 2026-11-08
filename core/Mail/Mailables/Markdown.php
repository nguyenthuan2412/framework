<?php

namespace Ryra\Core\Mail\Mailables;

use League\CommonMark\CommonMarkConverter;

class Markdown extends Html
{
    protected static $converter = null;

    public function __construct(protected string $markdown) {}

    public function __tostring(): string
    {
        if (is_null(static::$converter)) {
            static::$converter = new CommonMarkConverter();
        }

        return (string) static::$converter->convert($this->markdown);
    }
}