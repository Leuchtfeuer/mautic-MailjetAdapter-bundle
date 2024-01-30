<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Functional\Mailer\Transport;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetSmtpTransport;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class MailjetSmtpTransportTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        $this->configParams['mailer_dsn']            = MailjetSmtpTransport::SCHEME.'://user:pass@host:25';
        $this->configParams['messenger_dsn_email']   = 'sync://';
        $this->configParams['mailer_from_email']     = 'admin@mautic.test';
        $this->configParams['mailer_from_name']      = 'Admin';

        parent::setUp();
    }

    /**
     * @dataProvider dataForEmailDnsConfiguration
     */
    public function testEmailDnsConfiguration(string $field, string $expectedValidation): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/s/config/edit');
        $this->assertTrue($this->client->getResponse()->isOk());

        $data = [
            'config[emailconfig][mailer_dsn]['.$field.']' => '',
        ];

        $form = $crawler->selectButton('config[buttons][save]')->form();
        $form->setValues($data);

        // Check if there is the given validation error
        $crawler = $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isOk());
        $this->assertStringContainsString($expectedValidation, $crawler->html());
    }

    /**
     * @return array<string, string[]>
     */
    public function dataForEmailDnsConfiguration(): iterable
    {
        yield 'Empty schema' => [
            'scheme',
            'mailer DSN must contain a scheme.',
        ];

        yield 'Empty User' => [
            'user',
            'User is not set.',
        ];

        yield 'Empty Password' => [
            'password',
            'Password is not set.',
        ];
    }

    public function testSendEmail(): void
    {
        $lead = new Lead();
        $lead->setEmail('contact@an.email');

        $this->em->persist($lead);
        $this->em->flush();

        $this->client->request(Request::METHOD_GET, "/s/contacts/email/{$lead->getId()}");
        $this->assertTrue($this->client->getResponse()->isOk());

        $newContent = json_decode($this->client->getResponse()->getContent(), true)['newContent'];
        $crawler    = new Crawler($newContent, $this->client->getInternalRequest()->getUri());
        $form       = $crawler->selectButton('Send')->form();
        $form->setValues(
            [
                'lead_quickemail[subject]' => 'Hello there!',
                'lead_quickemail[body]'    => 'This is test body for {contactfield=email}!',
            ]
        );

        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isOk());

        self::assertQueuedEmailCount(1);

        $email      = self::getMailerMessage();
        $userHelper = static::getContainer()->get(UserHelper::class);
        $user       = $userHelper->getUser();

        $this->assertSame('Hello there!', $email->getSubject());
        $this->assertStringContainsString('This is test body for contact@an.email!', $email->getHtmlBody());
        $this->assertSame('This is test body for contact@an.email!', $email->getTextBody());
        /** @phpstan-ignore-next-line */
        $this->assertSame('contact@an.email', $email->getMetadata()['contact@an.email']['tokens']['{contactfield=email}']);
        $this->assertCount(1, $email->getFrom());
        $this->assertSame($user->getName(), $email->getFrom()[0]->getName());
        $this->assertSame($user->getEmail(), $email->getFrom()[0]->getAddress());
        $this->assertCount(1, $email->getTo());
        $this->assertSame('', $email->getTo()[0]->getName());
        $this->assertSame($lead->getEmail(), $email->getTo()[0]->getAddress());
        $this->assertCount(1, $email->getReplyTo());
        $this->assertSame('', $email->getReplyTo()[0]->getName());
    }
}
