<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Unit\Mailer\Transport;

use Mautic\EmailBundle\Entity\EmailRepository;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use Mautic\EmailBundle\Model\EmailStatModel;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\EmailBundle\MonitoredEmail\Search\ContactFinder;
use Mautic\LeadBundle\Model\DoNotContact;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetApiTransport;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @var MockObject&HttpClientInterface
     */
    private MockObject $httpClientMock;
    private MailjetApiTransport $transport;
    /**
     * @var MockObject&SentMessage
     */
    private MockObject $sentMessageMock;
    /**
     * @var MockObject&ResponseInterface
     */
    private MockObject $responseMock;
    /**
     * @var MockObject&Envelope
     */
    private MockObject $envelopeMock;

    protected function setUp(): void
    {
        $this->httpClientMock        = $this->createMock(HttpClientInterface::class);
        $this->sentMessageMock       = $this->createMock(SentMessage::class);
        $this->responseMock          = $this->createMock(ResponseInterface::class);
        $this->envelopeMock          = $this->createMock(Envelope::class);

        $transportCallback = new TransportCallback(
            $this->createStub(DoNotContact::class),
            $this->createStub(ContactFinder::class),
            $this->createStub(EmailStatModel::class)
        );

        $this->transport = new MailjetApiTransport(
            'user',
            'pass',
            true,
            $transportCallback,
            $this->httpClientMock,
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(EmailRepository::class)
        );
    }

    public function testSendEmailWhenEmailIsNotMauticMessage(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Message must be an instance of '.MauticMessage::class);

        $mauticMessage = $this->createStub(Email::class);

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

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSendEmailWithMoreThanOneReplyToAddressIsPresent(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Mailjet\'s API only supports one Reply-To email, 3 given.');

        $mauticMessage = $this->getMauticMessage();
        $mauticMessage->addReplyTo('reply1@mautic.com', 'reply2@mautic.com');

        $this->envelopeMock
            ->method('getSender')
            ->willReturn(new Address('from@mautic.com', 'From Name'));
        $this->envelopeMock
            ->method('getRecipients')
            ->willReturn([new Address('to@mautic.com', 'To Name')]);

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

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testSendEmail(): void
    {
        $mauticMessage = $this->getMauticMessage();

        $this->sentMessageMock
            ->method('getOriginalMessage')
            ->willReturn($mauticMessage);
        $this->responseMock
            ->method('getStatusCode')
            ->willReturn(200);

        $this->envelopeMock
            ->method('getSender')
            ->willReturn(new Address('from@mautic.com', 'From Name'));
        $this->envelopeMock
            ->method('getRecipients')
            ->willReturn([new Address('to@mautic.com', 'To Name')]);

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

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @param array<string, int|string|mixed> $data
     * @param string[]                        $expected
     */
    #[DataProvider('dataForSendEmailWhenErrorInData')]
    public function testSendEmailWhenErrorInData(array $data, array $expected): void
    {
        $this->expectException($expected['exceptionClass']);
        $this->expectExceptionMessage($expected['exceptionMessage']);

        $mauticMessage = $this->getMauticMessage();

        $this->sentMessageMock
            ->method('getOriginalMessage')
            ->willReturn($mauticMessage);

        $this->responseMock
            ->method('toArray')
            ->willReturn($data['body']);

        $this->responseMock
            ->method('getStatusCode')
            ->willReturn(400);

        $this->envelopeMock
            ->method('getSender')
            ->willReturn(new Address('from@mautic.com', 'From Name'));
        $this->envelopeMock
            ->method('getRecipients')
            ->willReturn([new Address('to@mautic.com', 'To Name')]);

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

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    /**
     * @return iterable<string, array<int, array<string, mixed>>>
     */
    public static function dataForSendEmailWhenErrorInData(): iterable
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

    /**
     * @param array<mixed> $args
     *
     * @throws \ReflectionException
     */
    private function invokeInaccessibleMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object::class);
        $method     = $reflection->getMethod($methodName);

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
