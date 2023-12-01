<?php

declare(strict_types=1);

namespace MauticPlugin\MailjetBundle\Tests\Unit\Mailer\Factory;

use MauticPlugin\MailjetBundle\Mailer\Factory\MailjetTransportFactory;
use MauticPlugin\MailjetBundle\Mailer\Transport\MailjetApiTransport;
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

    /**
     * @param array<string, int|string|null> $data
     * @param array<string, int|string>      $expected
     *
     * @dataProvider dataTransportDetailsWithExceptions
     */
    public function testCreateTransportWhenExceptionsOccurs(array $data, array $expected): void
    {
        $this->expectException($expected['exception']);
        $this->expectExceptionMessage($expected['exception_message']);

        $dsn = new Dsn(
            $data['scheme'],
            $data['host'],
            $data['user'],
            $data['password'],
            $data['port'],
        );

        $mailjetTransport = $this->mailjetTransportFactory->create($dsn);
        Assert::assertInstanceOf($expected['instance_of'], $mailjetTransport);
    }

    /**
     * @return iterable<string, array<int, array<string, int|string|null>>>
     */
    public function dataTransportDetailsWithExceptions(): iterable
    {
        yield 'SMTP when User and Password are null' => [
            // Dsn Details
            [
                'scheme'   => MailjetSmtpTransport::MAUTIC_MAILJET_SMTP_SCHEME,
                'host'     => MailjetSmtpTransport::MAILJET_HOST,
                'user'     => null,
                'password' => null,
                'port'     => MailjetSmtpTransport::MAILJET_DEFAULT_PORT,
            ],
            // expected
            [
                'exception'         => IncompleteDsnException::class,
                'exception_message' => 'User is not set.',
                'instance_of'       => MailjetSmtpTransport::class,
            ],
        ];

        yield 'SMTP when Password is null' => [
            // Dsn Details
            [
                'scheme'   => MailjetSmtpTransport::MAUTIC_MAILJET_SMTP_SCHEME,
                'host'     => MailjetSmtpTransport::MAILJET_HOST,
                'user'     => 'user',
                'password' => null,
                'port'     => MailjetSmtpTransport::MAILJET_DEFAULT_PORT,
            ],
            // expected
            [
                'exception'         => IncompleteDsnException::class,
                'exception_message' => 'Password is not set.',
                'instance_of'       => MailjetSmtpTransport::class,
            ],
        ];

        yield 'SMTP when wrong scheme' => [
            // Dsn Details
            [
                'scheme'   => 'wrong',
                'host'     => MailjetSmtpTransport::MAILJET_HOST,
                'user'     => 'user',
                'password' => 'pass',
                'port'     => MailjetSmtpTransport::MAILJET_DEFAULT_PORT,
            ],
            // expected
            [
                'exception'         => UnsupportedSchemeException::class,
                'exception_message' => 'The "wrong" scheme is not supported; supported schemes for mailer "mailjet" are: "mautic+mailjet+api", "mautic+mailjet+smtp".',
                'instance_of'       => MailjetSmtpTransport::class,
            ],
        ];

        yield 'API when User and Password are null' => [
            // Dsn Details
            [
                'scheme'   => MailjetAPITransport::SCHEME,
                'host'     => MailjetAPITransport::HOST,
                'user'     => null,
                'password' => null,
                'port'     => null,
            ],
            // expected
            [
                'exception'         => IncompleteDsnException::class,
                'exception_message' => 'User is not set.',
                'instance_of'       => MailjetAPITransport::class,
            ],
        ];

        yield 'API when Password is null' => [
            // Dsn Details
            [
                'scheme'   => MailjetAPITransport::SCHEME,
                'host'     => MailjetAPITransport::HOST,
                'user'     => 'user',
                'password' => null,
                'port'     => null,
            ],
            // expected
            [
                'exception'         => IncompleteDsnException::class,
                'exception_message' => 'Password is not set.',
                'instance_of'       => MailjetAPITransport::class,
            ],
        ];

        yield 'API when wrong scheme' => [
            // Dsn Details
            [
                'scheme'   => 'wrong',
                'host'     => MailjetAPITransport::HOST,
                'user'     => 'user',
                'password' => 'pass',
                'port'     => null,
            ],
            // expected
            [
                'exception'         => UnsupportedSchemeException::class,
                'exception_message' => 'The "wrong" scheme is not supported; supported schemes for mailer "mailjet" are: "mautic+mailjet+api", "mautic+mailjet+smtp".',
                'instance_of'       => MailjetSmtpTransport::class,
            ],
        ];
    }
}
