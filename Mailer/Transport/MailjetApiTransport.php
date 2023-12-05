<?php

declare(strict_types=1);

namespace MauticPlugin\MailjetBundle\Mailer\Transport;

use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MailjetApiTransport extends AbstractApiTransport
{
    public const SCHEME       = 'mautic+mailjet+api';
    public const HOST         = 'api.mailjet.com';
    private const API_VERSION = '3.1';

    private const FORBIDDEN_HEADERS = [
        'Date', 'X-CSA-Complaints', 'Message-Id', 'X-MJ-StatisticsContactsListID',
        'DomainKey-Status', 'Received-SPF', 'Authentication-Results', 'Received',
        'From', 'Sender', 'Subject', 'To', 'Cc', 'Bcc', 'Reply-To', 'Return-Path', 'Delivered-To', 'DKIM-Signature',
        'X-Feedback-Id', 'X-Mailjet-Segmentation', 'List-Id', 'X-MJ-MID', 'X-MJ-ErrorMessage',
        'X-Mailjet-Debug', 'User-Agent', 'X-Mailer', 'X-MJ-WorkflowID',
    ];
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

        $this->host = self::HOST;
    }

    public function __toString(): string
    {
        return sprintf(self::SCHEME.'://%s', $this->getEndpoint().($this->sandbox ? '?sandbox=true' : ''));
    }

    private function getEndpoint(): string
    {
        return $this->host.($this->port ? ':'.$this->port : '');
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        try {
            // Build payload
            $payload = $this->preparePayload($email, $envelope);

            // Post payload
            $response = $this->sendMessage($payload);

            // Log the payload.
        } catch (\Throwable $e) {
            throw $e;
        }

        return $response;
    }

    /**
     * @param array<string, array<int, array<string, array<string[]|string>|resource|string|null>>|bool> $payload
     *
     * @throws TransportExceptionInterface
     */
    private function sendMessage(array $payload): ResponseInterface
    {
        return $this->client->request(
            'POST',
            sprintf('https://%s/v%s/send', $this->getEndpoint(), self::API_VERSION),
            [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'auth_basic' => $this->user.':'.$this->password,
                'json'       => $payload,
            ]
        );
    }

    /**
     * @return array<string, array<int, array<string, array<string[]|string>|resource|string|null>>|bool>
     */
    private function preparePayload(Email $email, Envelope $envelope): array
    {
        if (!$email instanceof MauticMessage) {
            throw new TransportException(sprintf('Message must be an instance of %s', MauticMessage::class));
        }

        $attachments = $this->prepareAttachments($email);
        $message     = [
            'From'        => $this->formatAddress($envelope->getSender()),
            'To'          => $this->formatAddresses($this->getRecipients($email, $envelope)),
            'Subject'     => $email->getSubject(),
            'Attachments' => $attachments,
            'TextPart'    => $email->getTextBody(),
            'HTMLPart'    => $email->getHtmlBody(),
        ];

        if ($emails = $email->getCc()) {
            $message['Cc'] = $this->formatAddresses($emails);
        }

        if ($emails = $email->getBcc()) {
            $message['Bcc'] = $this->formatAddresses($emails);
        }

        if ($emails = $email->getReplyTo()) {
            if (1 < $length = \count($emails)) {
                throw new TransportException(sprintf('Mailjet\'s API only supports one Reply-To email, %d given.', $length));
            }
            $message['ReplyTo'] = $this->formatAddress($emails[0]);
        }

        if ($headers = $this->prepareHeaders($email)) {
            $message['Headers'] = $headers;
        }

        return [
            'Messages'    => [$message],
            'SandBoxMode' => $this->sandbox,
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function prepareAttachments(MauticMessage $message): array
    {
        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            $headers  = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $attachments[] = [
                'ContentType'   => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
                'Filename'      => $filename,
                'Base64Content' => $attachment->bodyToString(),
            ];
        }

        return $attachments;
    }

    /**
     * @return string[]
     */
    private function formatAddress(Address $address): array
    {
        return [
            'Email' => $address->getAddress(),
            'Name'  => $address->getName(),
        ];
    }

    /**
     * @param Address[] $addresses
     *
     * @return array<int, string[]>
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(\Closure::fromCallable([$this, 'formatAddress']), $addresses);
    }

    /**
     * @return string[]
     */
    private function prepareHeaders(MauticMessage $message): array
    {
        $headers = [];
        foreach ($message->getHeaders()->all() as $header) {
            if (\in_array($header->getName(), self::FORBIDDEN_HEADERS, true)) {
                continue;
            }

            $headers[$header->getName()] = $header->getBodyAsString();
        }

        // Add CustomID
        if ($message->getLeadIdHash()) {
            $headers['CustomID'] = $message->getLeadIdHash().'-'.current($message->getTo())->getAddress();
        }

        return $headers;
    }
}
