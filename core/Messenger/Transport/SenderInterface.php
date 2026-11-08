<?php

namespace Ryra\Core\Messenger\Transport;

use Ryra\Core\Messenger\Envelope;

interface SenderInterface
{
    public function send(Envelope $envelope): Envelope;
}