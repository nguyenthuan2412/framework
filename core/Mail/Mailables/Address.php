<?php

namespace Ryra\Core\Mail\Mailables;

class Address
{
    public function __construct(public readonly string $address, public readonly string $name = '') {}
}