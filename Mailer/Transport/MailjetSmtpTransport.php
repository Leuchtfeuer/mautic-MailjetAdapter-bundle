<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport;

use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\RawMessage;

class MailjetSmtpTransport extends EsmtpTransport
{
    public const HOST         = 'in-v3.mailjet.com';
    public const DEFAULT_PORT = 465;
    public const SCHEME       = 'mautic+mailjet+smtp';

    public function __construct(
        string $user,
        string $password,
        int $port,
        EventDispatcherInterface $dispatcher = null,
        LoggerInterface $logger = null
    ) {
        parent::__construct(self::HOST, $port, true, $dispatcher, $logger);

        $this->setUsername($user);
        $this->setPassword($password);
    }

    public function send(RawMessage $message, Envelope $envelope = null): ?SentMessage
    {
        // add leadIdHash to track this email
        if ($message instanceof MauticMessage && $message->getLeadIdHash()) {
            $message->getHeaders()->remove('X-MJ-CUSTOMID');
            $message->getHeaders()->addTextHeader('X-MJ-CUSTOMID', $message->getLeadIdHash().'-'.current($message->getTo())->getAddress());
        }

        return parent::send($message, $envelope);
    }
}
