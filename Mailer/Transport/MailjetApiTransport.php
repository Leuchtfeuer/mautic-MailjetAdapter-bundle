<?php

declare(strict_types=1);

namespace MauticPlugin\MailjetBundle\Mailer\Transport;

use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportInterface;
use Mautic\EmailBundle\Mailer\Transport\TokenTransportTrait;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MailjetApiTransport extends AbstractApiTransport implements TokenTransportInterface
{
    use TokenTransportTrait;

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
        private TransportCallback $callback,
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

    public function getMaxBatchLimit(): int
    {
        return 5000;
    }

    public function __toString(): string
    {
        return sprintf(self::SCHEME.'://%s', $this->getEndpoint().($this->sandbox ? '?sandbox=true' : ''));
    }

    private function getEndpoint(): string
    {
        return $this->host.($this->port ? ':'.$this->port : '');
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        try {
            // Build payload
            $payload = $this->preparePayload($email, $envelope);

            // Post payload
            $response = $this->sendMessage($payload);

            // Log the payload.
            $this->processResponse($response, $email, $payload);
        } catch (\Exception $e) {
            throw new TransportException($e->getMessage());
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
     * @return array<string, array<int, array<string, mixed>>|bool>
     */
    private function preparePayload(Email $email, Envelope $envelope): array
    {
        if (!$email instanceof MauticMessage) {
            throw new TransportException(sprintf('Message must be an instance of %s', MauticMessage::class));
        }

        $attachments = $this->prepareAttachments($email);
        $tokens      = $this->prepareTokens($email);
        $message     = [
            'From'             => $this->formatAddress($envelope->getSender()),
            'To'               => $this->formatAddresses($this->getRecipients($email, $envelope)),
            'Subject'          => $email->getSubject(),
            'Attachments'      => $attachments,
            'TextPart'         => $email->getTextBody(),
            'HTMLPart'         => $email->getHtmlBody(),
            'Variables'        => $tokens,
            'TemplateLanguage' => true,
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

        // Add CustomID
        if ($email->getLeadIdHash()) {
            $message['CustomID'] = $email->getLeadIdHash().'-'.current($email->getTo())->getAddress();
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

        return $headers;
    }

    /**
     * @param array<string, array<int, array<string, array<string[]|string>|resource|string|null>>|bool> $message
     *
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    private function processResponse(ResponseInterface $response, Email $email, array $message = []): void
    {
        $statusCode = $response->getStatusCode();
        $result     = $response->toArray(false);

        if (200 !== $statusCode) {
            $errorDetails = '';
            $errors       = $result['Messages'][0]['Errors'] ?? [$result];
            foreach ($errors as $error) {
                $errorDetails .= sprintf(
                    '"%s%s (code %s)"',
                    !empty($error['ErrorRelatedTo']) ? 'Related to properties {'.implode(', ', $error['ErrorRelatedTo']).'}:' : '',
                    $error['ErrorMessage'],
                    $error['StatusCode']
                ).PHP_EOL;
            }

            $errorMessage = sprintf('Unable to send an email: %s', $errorDetails);

            $this->getLogger()->error($errorMessage);

            $emailAddress = $message['Messages'][0]['To'][0]['Email'];

            \assert($email instanceof MauticMessage);
            $metadata = $email->getMetadata();

            if (isset($metadata[$emailAddress]['leadId'])) {
                $emailId = !empty($metadata[$emailAddress]['emailId']) ? (int) $metadata[$emailAddress]['emailId'] : null;
                $this->callback->addFailureByContactId(
                    $metadata[$emailAddress]['leadId'],
                    $errorMessage,
                    DoNotContact::BOUNCED,
                    $emailId
                );
            }

            throw new HttpTransportException($errorMessage, $response);
        }
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function prepareTokens(MauticMessage &$email): array
    {
        $metadata  = $email->getMetadata();
        $retTokens = [];

        if (!empty($metadata)) {
            $metadataSet  = current($metadata);
            $tokens       = (!empty($metadataSet['tokens'])) ? $metadataSet['tokens'] : [];
            $mauticTokens = array_keys($tokens);

            $mergeVarPlaceholders = [];

            $toAddresses = $email->getTo();
            $toAddress   = reset($toAddresses);
            $address     = $toAddress->getAddress();

            foreach ($mauticTokens as $token) {
                $newToken                      = strtoupper(preg_replace('/[^a-z0-9]+/i', '', $token));
                $mergeVarPlaceholders[$token]  = sprintf('{{var:%s}}', $newToken);
                $retTokens[$newToken]          = $metadata[$address]['tokens'][$token];
            }

            if (!empty($mauticTokens)) {
                MailHelper::searchReplaceTokens($mauticTokens, $mergeVarPlaceholders, $email);
            }

            // We need to replace the token as per the Mailjet personalization requirement.
            // If there is token used in subject  e.g. "Hello {contactfield=firstname},"
            // we should make the Mailjet way.
            if (isset($retTokens['SUBJECT'])) {
                $retTokens['SUBJECT'] = $email->getSubject();
            }
        }

        return $retTokens;
    }
}
