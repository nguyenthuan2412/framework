<?php

namespace Ryra\Core\Mail;

use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Ryra\Core\App;
use Ryra\Core\Coroutine\Coroutine;
use Ryra\Core\Mail\Mailables\Text;
use Ryra\Core\Mail\Mailables\Html;

class Mailer implements MailerInterface
{
    protected $email;

    public function __construct(protected TransportInterface $transport)
    {
        $this->email = new Email();
        $mailFrom = config('mail.from.address', null);
        if (!empty($mailFrom)) {
            $this->email->from(new Address($mailFrom, config('mail.from.name', '')));
        }
    }

    public function send(?Mailable $mail = null): void
    {
        if ($mail) {
            $content = $mail->content();
            if ($content instanceof Html) {
                $this->email->html((string) $content);
            } else if ($content instanceof Text) {
                $this->email->text((string) $content);
            }
    
            $envelope = $mail->envelope();
            if (!is_null($envelope)) {
                if (
                    empty($this->email->getSubject())
                    && !empty($envelope->subject)
                ) {
                    $this->email->subject($envelope->subject);
                }
    
                if (
                    empty($this->email->getFrom())
                    && !empty($envelope->from)
                ) {
                    $this->email->from($envelope->from);
                }
    
                if (
                    empty($this->email->getTo())
                    && !empty($envelope->to)
                ) {
                    $this->email->to(...$envelope->to);
                }
    
                if (
                    empty($this->email->getCc())
                    && !empty($envelope->cc)
                ) {
                    $this->email->cc(...$envelope->cc);
                }
    
                if (
                    empty($this->email->getBcc())
                    && !empty($envelope->bcc)
                ) {
                    $this->email->bcc(...$envelope->bcc);
                }
    
                if (
                    empty($this->email->getReplyTo())
                    && !empty($envelope->replyTo)
                ) {
                    $this->email->replyTo(...$envelope->replyTo);
                }
    
                if (!empty($envelope->using)) {
                    foreach ($envelope->using as $callback) {
                        $callback($this->email);
                    }
                }
            }
    
            $attachments = $mail->attachments();
            if (!is_null($attachments)) {
                foreach ($attachments as $attachment) {
                    $this->email->addPart(
                        new DataPart(
                            $attachment->resource,
                            $attachment->name,
                            $attachment->mine
                        )
                    );
                }
            }

            $headers = $mail->headers();
            if (!is_null($headers)) {
                $mailHeaders = $this->email->getHeaders();
                if (!empty($headers->messageId)) {
                    $mailHeaders->addTextHeader('Message-ID', $headers->messageId);
                }

                if (!empty($headers->references)) {
                    $mailHeaders->addIdHeader('References', $headers->references);
                }

                if (!empty($headers->text)) {
                    foreach ($headers->text as $key => $value) {
                        $mailHeaders->addTextHeader($key, $value);
                    }
                }
            }
        }
        
        $this->transport->send($this->email);
    }

    public function getSymfonyTransport()
    {
        return $this->transport;
    }

    public function defer(?Mailable $mail = null): void
    {
        Coroutine::defer(fn () => $this->send($mail));
    }

    public function queue(?Mailable $mail = null): void
    {
        
    }

    public function view(string $path, array $params = []): static
    {
        $this->email->html(App::service('twig.environment')->render($path, $params));
        return $this;
    }

    public function __call(string $name, array $arguments): static
    {
        $this->email->$name(...$arguments);
        return $this;
    }
}