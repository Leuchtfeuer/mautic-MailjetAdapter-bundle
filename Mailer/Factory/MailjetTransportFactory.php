<?php

declare(strict_types=1);

namespace MauticPlugin\MailjetBundle\Mailer\Factory;

use MauticPlugin\MailjetBundle\Mailer\Transport\MailjetSmtpTransport;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MailjetTransportFactory extends AbstractTransportFactory
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        HttpClientInterface $client = null,
        LoggerInterface $logger = null
    ) {
        parent::__construct($eventDispatcher, $client, $logger);
    }

    /**
     * @return string[]
     */
    protected function getSupportedSchemes(): array
    {
        return [MailjetSmtpTransport::MAUTIC_MAILJET_SMTP_SCHEME];
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $user     = $this->getUser($dsn);
        $password = $this->getPassword($dsn);

        if (MailjetSmtpTransport::MAUTIC_MAILJET_SMTP_SCHEME === $dsn->getScheme() && $user && $password) {
            return new MailjetSmtpTransport($user, $password, $dsn->getPort(MailjetSmtpTransport::MAILJET_DEFAULT_PORT), $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'mailjet', $this->getSupportedSchemes());
    }
}
