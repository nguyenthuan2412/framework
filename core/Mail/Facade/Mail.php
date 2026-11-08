<?php

namespace Ryra\Core\Mail\Facade;

use Ryra\Core\App;
use Ryra\Core\Support\Facade;
use Ryra\Core\Mail\MailerInterface;

/**
 * @method static \Ryra\Core\Mail\Mailer html($body, string $charset = 'utf-8')
 * @method static \Ryra\Core\Mail\Mailer text($body, string $charset = 'utf-8')
 * @method static \Ryra\Core\Mail\Mailer subject(string $subject)
 * @method static \Ryra\Core\Mail\Mailer from(\Symfony\Component\Mime\Address|string ...$addresses)
 * @method static \Ryra\Core\Mail\Mailer to(\Symfony\Component\Mime\Address|string ...$addresses)
 * @method static \Ryra\Core\Mail\Mailer cc(\Symfony\Component\Mime\Address|string ...$addresses)
 * @method static \Ryra\Core\Mail\Mailer bcc(\Symfony\Component\Mime\Address|string ...$addresses)
 * @method static \Ryra\Core\Mail\Mailer replyTo(\Symfony\Component\Mime\Address|string ...$addresses)
 * @method static \Ryra\Core\Mail\Mailer addTextHeader(string $name, string $value)
 * @method static \Ryra\Core\Mail\Mailer addIdHeader(string $name, string|array $ids)
 * @method static void send(?\Ryra\Core\Mail\Mailable $mail = null)
 * @method static void queue(?\Ryra\Core\Mail\Mailable $mail = null)
 * @method static void defer(?\Ryra\Core\Mail\Mailable $mail = null)
 * @method static mixed getSymfonyTransport()
 */
class Mail extends Facade
{
    protected static function getFacadeAccessor(): string|object
    {
        return 'mailer';
    }

    public static function driver(string $driver): MailerInterface
    {
        return App::service('mailer_factory')->driver($driver);
    }
}