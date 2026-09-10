<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Unit\Mailer\Factory;

use Mautic\EmailBundle\Entity\EmailRepository;
use Mautic\EmailBundle\Model\EmailStatModel;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\EmailBundle\MonitoredEmail\Search\ContactFinder;
use Mautic\LeadBundle\Model\DoNotContact;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Factory\MailjetTransportFactory;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetApiTransport;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetSmtpTransport;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $transportCallback = new TransportCallback(
            $this->createStub(DoNotContact::class),
            $this->createStub(ContactFinder::class),
            $this->createStub(EmailStatModel::class)
        );

        $this->mailjetTransportFactory = new MailjetTransportFactory(
            $transportCallback,
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(EmailRepository::class),
            $this->createStub(HttpClientInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    /**
     * @param array<string, int|string|null> $data
     * @param array<string, int|string>      $expected
     */
    #[DataProvider('dataTransportDetailsWithExceptions')]
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
        $this->assertInstanceOf($expected['instance_of'], $mailjetTransport);
    }

    /**
     * @return iterable<string, array<int, array<string, int|string|null>>>
     */
    public static function dataTransportDetailsWithExceptions(): iterable
    {
        yield 'SMTP when User and Password are null' => [
            // Dsn Details
            [
                'scheme'   => MailjetSmtpTransport::SCHEME,
                'host'     => MailjetSmtpTransport::HOST,
                'user'     => null,
                'password' => null,
                'port'     => MailjetSmtpTransport::DEFAULT_PORT,
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
                'scheme'   => MailjetSmtpTransport::SCHEME,
                'host'     => MailjetSmtpTransport::HOST,
                'user'     => 'user',
                'password' => null,
                'port'     => MailjetSmtpTransport::DEFAULT_PORT,
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
                'host'     => MailjetSmtpTransport::HOST,
                'user'     => 'user',
                'password' => 'pass',
                'port'     => MailjetSmtpTransport::DEFAULT_PORT,
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
                'scheme'   => MailjetApiTransport::SCHEME,
                'host'     => MailjetApiTransport::HOST,
                'user'     => null,
                'password' => null,
                'port'     => null,
            ],
            // expected
            [
                'exception'         => IncompleteDsnException::class,
                'exception_message' => 'User is not set.',
                'instance_of'       => MailjetApiTransport::class,
            ],
        ];

        yield 'API when Password is null' => [
            // Dsn Details
            [
                'scheme'   => MailjetApiTransport::SCHEME,
                'host'     => MailjetApiTransport::HOST,
                'user'     => 'user',
                'password' => null,
                'port'     => null,
            ],
            // expected
            [
                'exception'         => IncompleteDsnException::class,
                'exception_message' => 'Password is not set.',
                'instance_of'       => MailjetApiTransport::class,
            ],
        ];

        yield 'API when wrong scheme' => [
            // Dsn Details
            [
                'scheme'   => 'wrong',
                'host'     => MailjetApiTransport::HOST,
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
