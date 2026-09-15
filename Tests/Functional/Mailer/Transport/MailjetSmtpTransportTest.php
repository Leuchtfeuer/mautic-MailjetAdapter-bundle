<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Functional\Mailer\Transport;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
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
        $this->assertInstanceOf(MauticMessage::class, $email);
        $userHelper = self::getContainer()->get(UserHelper::class);
        $user       = $userHelper->getUser();

        $this->assertSame('Hello there!', $email->getSubject());
        $this->assertStringContainsString('This is test body for contact@an.email!', (string) $email->getHtmlBody());
        $this->assertSame('This is test body for contact@an.email!', $email->getTextBody());
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
