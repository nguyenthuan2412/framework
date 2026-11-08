<?php

namespace Ryra\Core\Mail;

use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;
use Symfony\Component\Mailer\Transport\Dsn;
use Ryra\Core\Contracts\Config\Repository;
use Ryra\Core\Macro\Macroable;
use Ryra\Core\Support\Manager;
use Ryra\Core\Log\Log;

class MailerFactory extends Manager
{
    use Macroable;

    public function __construct(protected Repository $config) {}

    public function getDefaultDriver(): string
    {
        return $this->config->get('mail.default');
    }

    protected function getConfigDriver(string $driver): array
    {
        return $this->config->get("mail.mailers.$driver");
    }

    public function createSmtpDriver(bool $returnMailer = true): Mailer|TransportInterface
    {
        $config = $this->getConfigDriver('smtp');
        $dsn = new Dsn(
            $config['scheme'],
            $config['host'],
            $config['username'],
            $config['password']
        );
        $transport = (new EsmtpTransportFactory(logger: Log::get()))->create($dsn);
        return $this->getMailerOrTransport($transport, $returnMailer);
    }

    public function createMailtrapDriver(bool $returnMailer = true): Mailer|TransportInterface
    {
        $config = $this->getConfigDriver('mailtrap');
        $dsn = new Dsn($config['scheme'], $config['host']);
        $transport = (new MailtrapTransportFactory(logger: Log::get()))->create($dsn);
        return $this->getMailerOrTransport($transport, $returnMailer);
    }

    public function createSendgridDriver(bool $returnMailer = true)
    {
        $config = $this->getConfigDriver('sendgrid');
        $dsn = new Dsn($config['scheme'], 'default', $config['key']);
        $transport = (new SendgridTransportFactory(logger: Log::get()))->create($dsn);
        return $this->getMailerOrTransport($transport, $returnMailer);
    }

    protected function getMailerOrTransport(TransportInterface $transport, bool $returnMailer = true): Mailer|TransportInterface
    {
        if ($returnMailer) {
            return new Mailer($transport); 
        }

        return $transport;
    }

    public function createRoundrobinDriver(): Mailer
    {
        $drivers = config('mail.mailers.roundrobin');
        $transports = array_map(function ($driver) {
            return $this->driver($driver, [false]);
        }, $drivers);

        $transport = new RoundRobinTransport($transports);
        return $this->getMailerOrTransport($transport);
    }
}