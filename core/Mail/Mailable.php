<?php

namespace Ryra\Core\Mail;

use Ryra\Core\Mail\Mailables\ContentInterface;
use Ryra\Core\Mail\Mailables\Envelope;
use Ryra\Core\Mail\Mailables\Headers;

abstract class Mailable
{
    abstract public function content(): ContentInterface;

    public function envelope(): ?Envelope
    {
        return null;
    }

    public function headers(): ?Headers
    {
        return null;
    }

    public function attachments(): ?array
    {
        return null;
    }
}