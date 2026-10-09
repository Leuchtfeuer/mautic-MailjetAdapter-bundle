<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Unit\Mailer\Transport;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Entity\Email as EmailEntity;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetApiTransport;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetTransportCallback;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class MailjetApiTransportTest extends TestCase
{
    private HttpClientInterface|MockObject $httpClientMock;
    private MailjetApiTransport $transport;
    private SentMessage|MockObject $sentMessageMock;
    private ResponseInterface|MockObject $responseMock;
    private Envelope|MockObject $envelopeMock;
    private EntityManager|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->httpClientMock        = $this->createMock(HttpClientInterface::class);
        $this->sentMessageMock       = $this->createMock(SentMessage::class);
        $this->responseMock          = $this->createMock(ResponseInterface::class);
        $this->envelopeMock          = $this->createMock(Envelope::class);
        $this->entityManager         = $this->createMock(EntityManager::class);

        $transportCallbackMock = $this->createMock(MailjetTransportCallback::class);
        $eventDispatcherMock   = $this->createMock(EventDispatcherInterface::class);
        $loggerMock            = $this->createMock(LoggerInterface::class);
        $coreParameterHelper   = $this->createMock(CoreParametersHelper::class);

        $this->transport = new MailjetApiTransport(
            'user',
            'pass',
            true,
            $transportCallbackMock,
            $this->httpClientMock,
            $eventDispatcherMock,
            $loggerMock,
            $coreParameterHelper,
            $this->entityManager
        );
    }

    public function testSendEmailWhenEmailIsNotMauticMessage(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Message must be an instance of '.MauticMessage::class);

        $mauticMessage = $this->createMock(Email::class);

        /** @phpstan-ignore-next-line */
        $this->httpClientMock
            ->method('request')
            ->willReturn($this->responseMock);

        $response = $this->invokeInaccessibleMethod(
            $this->transport,
            'doSendApi',
            [
                $this->sentMessageMock,
                $mauticMessage,
                $this->envelopeMock,
            ]
        );

        Assert::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSendEmailWithMoreThanOneReplyToAddressIsPresent(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Mailjet\'s API only supports one Reply-To email, 3 given.');

        $mauticMessage = $this->getMauticMessage();
        $mauticMessage->addReplyTo('reply1@mautic.com', 'reply2@mautic.com');

        /** @phpstan-ignore-next-line */
        $this->envelopeMock
            ->method('getSender')
            ->willReturn(new Address('from@mautic.com', 'From Name'));
        /** @phpstan-ignore-next-line */
        $this->envelopeMock
            ->method('getRecipients')
            ->willReturn([new Address('to@mautic.com', 'To Name')]);

        /** @phpstan-ignore-next-line */
        $this->httpClientMock
            ->method('request')
            ->willReturn($this->responseMock);

        $response = $this->invokeInaccessibleMethod(
            $this->transport,
            'doSendApi',
            [
                $this->sentMessageMock,
                $mauticMessage,
                $this->envelopeMock,
            ]
        );

        Assert::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSendEmail(): void
    {
        $mauticMessage = $this->getMauticMessage();

        /** @phpstan-ignore-next-line */
        $this->sentMessageMock
            ->method('getOriginalMessage')
            ->willReturn($mauticMessage);
        /** @phpstan-ignore-next-line */
        $this->responseMock
            ->method('getStatusCode')
            ->willReturn(200);

        /** @phpstan-ignore-next-line */
        $this->envelopeMock
            ->method('getSender')
            ->willReturn(new Address('from@mautic.com', 'From Name'));
        /** @phpstan-ignore-next-line */
        $this->envelopeMock
            ->method('getRecipients')
            ->willReturn([new Address('to@mautic.com', 'To Name')]);

        /** @phpstan-ignore-next-line */
        $this->httpClientMock
            ->method('request')
            ->willReturn($this->responseMock);

        $response = $this->invokeInaccessibleMethod(
            $this->transport,
            'doSendApi',
            [
                $this->sentMessageMock,
                $mauticMessage,
                $this->envelopeMock,
            ]
        );

        Assert::assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @param array<string, int|string|mixed> $data
     * @param string[]                        $expected
     *
     * @dataProvider dataForSendEmailWhenErrorInData
     */
    public function testSendEmailWhenErrorInData(array $data, array $expected): void
    {
        $this->expectException($expected['exceptionClass']);
        $this->expectExceptionMessage($expected['exceptionMessage']);

        $mauticMessage = $this->getMauticMessage();

        /** @phpstan-ignore-next-line */
        $this->sentMessageMock
            ->method('getOriginalMessage')
            ->willReturn($mauticMessage);

        /** @phpstan-ignore-next-line */
        $this->responseMock
            ->method('toArray')
            ->willReturn($data['body']);

        /** @phpstan-ignore-next-line */
        $this->responseMock
            ->method('getStatusCode')
            ->willReturn(400);

        /** @phpstan-ignore-next-line */
        $this->envelopeMock
            ->method('getSender')
            ->willReturn(new Address('from@mautic.com', 'From Name'));
        /** @phpstan-ignore-next-line */
        $this->envelopeMock
            ->method('getRecipients')
            ->willReturn([new Address('to@mautic.com', 'To Name')]);

        /** @phpstan-ignore-next-line */
        $this->httpClientMock
            ->method('request')
            ->willReturn($this->responseMock);

        $response = $this->invokeInaccessibleMethod(
            $this->transport,
            'doSendApi',
            [
                $this->sentMessageMock,
                $mauticMessage,
                $this->envelopeMock,
            ]
        );

        Assert::assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function dataForSendEmailWhenErrorInData(): iterable
    {
        yield 'When email is without text and html' => [
            [
                'body' => json_decode('{"Messages": [{"Status": "error","Errors": [{"ErrorCode": "","StatusCode": 400,"ErrorMessage": "At least \"HTMLPart\", \"TextPart\" or \"TemplateID\" must be provided.","ErrorRelatedTo": ["TextPart","HTMLPart","TemplateID"]}]}]}', true),
            ],
            [
                'exceptionClass'   => TransportException::class,
                'exceptionMessage' => 'Unable to send an email: "Related to properties {TextPart, HTMLPart, TemplateID}:At least "HTMLPart", "TextPart" or "TemplateID" must be provided. (code 400)',
            ],
        ];

        yield 'When requested json has errors.' => [
            [
                'body' => json_decode('{"ErrorIdentifier":"","ErrorCode":"","StatusCode":400,"ErrorMessage":"Malformed JSON, please review the syntax and properties types."}', true),
            ],
            [
                'exceptionClass'   => TransportException::class,
                'exceptionMessage' => 'Unable to send an email: "Malformed JSON, please review the syntax and properties types. (code 400)',
            ],
        ];
    }

    public function testPreparePayloadUsesEntityFromWhenPlainAddress(): void
    {
        $emailEntity = new EmailEntity();
        $emailEntity->setFromAddress('entity@example.com');
        $emailEntity->setFromName('Entity Name');
        $this->mockEmailEntityLookup(42, $emailEntity);

        $message  = $this->getMauticMessageWithEmailId(42);
        $envelope = new Envelope(
            new Address('envelope@example.com', 'Envelope Name'),
            [new Address('to@mautic.com', 'To Name')]
        );

        $payload = $this->transport->preparePayloadFromMetadata($message->getMetadata(), $message, $envelope);

        Assert::assertSame('entity@example.com', $payload['Messages'][0]['From']['Email']);
        Assert::assertSame('Entity Name', $payload['Messages'][0]['From']['Name']);
    }

    public function testPreparePayloadFallsBackToEnvelopeWhenEntityFromIsTokenized(): void
    {
        $emailEntity = new EmailEntity();
        $emailEntity->setFromAddress('{contactfield=vbe_mail_contact}');
        $emailEntity->setFromName('{contactfield=firstname}');
        $this->mockEmailEntityLookup(42, $emailEntity);

        $message  = $this->getMauticMessageWithEmailId(42);
        $envelope = new Envelope(
            new Address('resolved@example.com', 'Resolved Name'),
            [new Address('to@mautic.com', 'To Name')]
        );

        $payload = $this->transport->preparePayloadFromMetadata($message->getMetadata(), $message, $envelope);

        Assert::assertSame('resolved@example.com', $payload['Messages'][0]['From']['Email']);
        Assert::assertSame('Resolved Name', $payload['Messages'][0]['From']['Name']);
    }

    public function testPreparePayloadFallsBackOnlyTokenizedPartsOfFrom(): void
    {
        $emailEntity = new EmailEntity();
        $emailEntity->setFromAddress('{contactfield=vbe_mail_contact}');
        $emailEntity->setFromName('Static Display Name');
        $this->mockEmailEntityLookup(42, $emailEntity);

        $message  = $this->getMauticMessageWithEmailId(42);
        $envelope = new Envelope(
            new Address('resolved@example.com', 'Envelope Name'),
            [new Address('to@mautic.com', 'To Name')]
        );

        $payload = $this->transport->preparePayloadFromMetadata($message->getMetadata(), $message, $envelope);

        Assert::assertSame('resolved@example.com', $payload['Messages'][0]['From']['Email']);
        Assert::assertSame('Static Display Name', $payload['Messages'][0]['From']['Name']);
    }

    public function testPreparePayloadFallsBackToMessageReplyToWhenEntityReplyToIsTokenized(): void
    {
        $emailEntity = new EmailEntity();
        $emailEntity->setFromAddress('entity@example.com');
        $emailEntity->setReplyToAddress('{contactfield=vbe_mail_contact}');
        $this->mockEmailEntityLookup(42, $emailEntity);

        $message = $this->getMauticMessageWithEmailId(42);
        $message->replyTo(new Address('message-reply@example.com', 'Message Reply'));
        $envelope = new Envelope(
            new Address('envelope@example.com', 'Envelope Name'),
            [new Address('to@mautic.com', 'To Name')]
        );

        $payload = $this->transport->preparePayloadFromMetadata($message->getMetadata(), $message, $envelope);

        Assert::assertSame('message-reply@example.com', $payload['Messages'][0]['ReplyTo']['Email']);
        Assert::assertSame('Message Reply', $payload['Messages'][0]['ReplyTo']['Name']);
    }

    public function testPreparePayloadUsesEntityReplyToWhenPlainAddress(): void
    {
        $emailEntity = new EmailEntity();
        $emailEntity->setFromAddress('entity@example.com');
        $emailEntity->setReplyToAddress('entity-reply@example.com');
        $this->mockEmailEntityLookup(42, $emailEntity);

        $message = $this->getMauticMessageWithEmailId(42);
        $message->replyTo(new Address('message-reply@example.com', 'Message Reply'));
        $envelope = new Envelope(
            new Address('envelope@example.com', 'Envelope Name'),
            [new Address('to@mautic.com', 'To Name')]
        );

        $payload = $this->transport->preparePayloadFromMetadata($message->getMetadata(), $message, $envelope);

        Assert::assertSame('entity-reply@example.com', $payload['Messages'][0]['ReplyTo']['Email']);
    }

    private function mockEmailEntityLookup(int $emailId, EmailEntity $emailEntity): void
    {
        /** @var EntityRepository&MockObject $repository */
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->atLeastOnce())
            ->method('find')
            ->with($emailId)
            ->willReturn($emailEntity);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with(EmailEntity::class)
            ->willReturn($repository);
    }

    private function getMauticMessageWithEmailId(int $emailId): MauticMessage
    {
        $mauticMessage = new MauticMessage();
        $mauticMessage->to(new Address('to@mautic.com', 'To Name'));
        $mauticMessage->subject('Hello');
        $mauticMessage->text('Body');
        $mauticMessage->html('<p>Body</p>');
        $mauticMessage->addMetadata('to@mautic.com', [
            'leadId'   => '123',
            'hashId'   => 'hash-123',
            'emailId'  => $emailId,
            'name'     => 'To Name',
            'tokens'   => [],
        ]);

        return $mauticMessage;
    }

    /**
     * @param array<mixed> $args
     *
     * @throws \ReflectionException
     */
    private function invokeInaccessibleMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    private function getMauticMessage(): MauticMessage
    {
        $mauticMessage = new MauticMessage();
        $mauticMessage->to(new Address('from@mautic.com', 'From Name'));
        $mauticMessage->replyTo(new Address('reply@mautic.com', 'Reply To Name'));
        $mauticMessage->to(new Address('to@mautic.com', 'To Name'));
        $mauticMessage->cc(new Address('cc@mautic.com', 'CC Name'));
        $mauticMessage->bcc(new Address('bcc@mautic.com', 'BCC Name'));
        $mauticMessage->updateLeadIdHash('LeadHash');
        $mauticMessage->addMetadata('to@mautic.com', ['leadId' => '123']);
        $mauticMessage->subject('abc');
        $mauticMessage->addMetadata('to@mautic.com', ['hashId' => '1234']);

        return $mauticMessage;
    }
}
