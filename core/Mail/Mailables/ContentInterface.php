<?php

namespace Ryra\Core\Mail\Mailables;

interface ContentInterface
{
    public function __tostring(): string;
}