<?php

declare(strict_types=1);

namespace MauticPlugin\MailjetBundle\Tests\Unit\Mailer\Factory;

use MauticPlugin\MailjetBundle\Mailer\Factory\MailjetTransportFactory;
use MauticPlugin\MailjetBundle\Mailer\Transport\MailjetSmtpTransport;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MailjetTransportFactoryTest extends TestCase
{
    private MailjetTransportFactory $mailjetTransportFactory;

    protected function setUp(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class);
        $httpClientMock      = $this->createMock(HttpClientInterface::class);
        $loggerMock          = $this->createMock(LoggerInterface::class);

        $this->mailjetTransportFactory = new MailjetTransportFactory($eventDispatcherMock, $httpClientMock, $loggerMock);
    }

    public function testCreateTransportWhenNoUserPasswordProvided(): void
    {
        $this->expectException(IncompleteDsnException::class);
        $this->expectExceptionMessage('User is not set.');

        $dsn = new Dsn(
            MailjetSmtpTransport::MAUTIC_MAILJET_SMTP_SCHEME,
            MailjetSmtpTransport::MAILJET_HOST,
            null,
            null,
            MailjetSmtpTransport::MAILJET_DEFAULT_PORT,
        );

        $mailjetTransport = $this->mailjetTransportFactory->create($dsn);
        Assert::assertInstanceOf(MailjetSmtpTransport::class, $mailjetTransport);
    }

    public function testCreateTransportWhenNoPasswordProvided(): void
    {
        $this->expectException(IncompleteDsnException::class);
        $this->expectExceptionMessage('Password is not set.');

        $dsn = new Dsn(
            MailjetSmtpTransport::MAUTIC_MAILJET_SMTP_SCHEME,
            MailjetSmtpTransport::MAILJET_HOST,
            'some',
            null,
            MailjetSmtpTransport::MAILJET_DEFAULT_PORT,
        );

        $mailjetTransport = $this->mailjetTransportFactory->create($dsn);
        Assert::assertInstanceOf(MailjetSmtpTransport::class, $mailjetTransport);
    }

    public function testCreateTransportWhenWrongScheme(): void
    {
        $this->expectException(UnsupportedSchemeException::class);
        $this->expectExceptionMessage('The "mautic+mailjet+smtpsomething" scheme is not supported; supported schemes for mailer "mailjet" are: "mautic+mailjet+smtp"');

        $dsn = new Dsn(
            MailjetSmtpTransport::MAUTIC_MAILJET_SMTP_SCHEME.'something',
            MailjetSmtpTransport::MAILJET_HOST,
            'some',
            'cred',
            MailjetSmtpTransport::MAILJET_DEFAULT_PORT,
        );

        $mailjetTransport = $this->mailjetTransportFactory->create($dsn);
        Assert::assertInstanceOf(MailjetSmtpTransport::class, $mailjetTransport);
    }
}
