<?php

declare(strict_types=1);

namespace MauticPlugin\MailjetBundle\Mailer\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MailjetApiTransport extends AbstractApiTransport
{
    public const SCHEME = 'mautic+mailjet+api';
    public const HOST   = 'api.mailjet.com';
    private string $user;
    private string $password;
    private bool $sandbox;

    public function __construct(
        string $user,
        string $password,
        bool $sandbox,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null,
        LoggerInterface $logger = null
    ) {
        $this->user     = $user;
        $this->password = $password;
        $this->sandbox  = $sandbox;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf(self::SCHEME.'://%s', $this->getEndpoint().($this->sandbox ? '?sandbox=true' : ''));
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        // TODO: Implement doSendApi() method.
    }
}
