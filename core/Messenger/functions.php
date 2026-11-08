<?php

use Ryra\Core\App;
use Ryra\Core\Messenger\Envelope;

function dispatch(object $message): Envelope
{
    return App::service('messenger.bus')->dispatch($message);
}