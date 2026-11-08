<?php

namespace Ryra\Core\Mail;

interface MailerInterface
{
    public function send(Mailable $mail): void;
}