<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Factory;

use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetApiTransport;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetSmtpTransport;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetTransportCallback;
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
        private MailjetTransportCallback $transportCallback,
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
        return [
            MailjetApiTransport::SCHEME,
            MailjetSmtpTransport::SCHEME,
        ];
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $user     = $this->getUser($dsn);
        $password = $this->getPassword($dsn);
        $sandbox  = filter_var($dsn->getOption('sandbox', false), \FILTER_VALIDATE_BOOL);

        if (MailjetSmtpTransport::SCHEME === $dsn->getScheme() && $user && $password) {
            return new MailjetSmtpTransport($user, $password, $dsn->getPort(MailjetSmtpTransport::DEFAULT_PORT), $this->dispatcher, $this->logger);
        }

        if (MailjetApiTransport::SCHEME === $dsn->getScheme() && $user && $password) {
            return new MailjetApiTransport($user, $password, $sandbox, $this->transportCallback, $this->client, $this->dispatcher, $this->logger);
        }

        throw new UnsupportedSchemeException($dsn, 'mailjet', $this->getSupportedSchemes());
    }
}
