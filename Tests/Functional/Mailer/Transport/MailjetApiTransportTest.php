<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Functional\Mailer\Transport;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Mailer\Message\MauticMessage;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetApiTransport;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Functional\CreateEntities;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MailjetApiTransportTest extends MauticMysqlTestCase
{
    use CreateEntities;

    protected function setUp(): void
    {
        $this->configParams['mailer_dsn']            = MailjetApiTransport::SCHEME.'://user:pass@default?sandbox=true';
        $this->configParams['messenger_dsn_email']   = 'sync://';
        $this->configParams['mailer_from_email']     = 'admin@mautic.test';
        $this->configParams['mailer_from_name']      = 'Admin';

        parent::setUp();
    }

    public function testSendEmail(): void
    {
        /** @var MockHttpClient $mockHttpClient */
        $mockHttpClient = self::getContainer()->get(HttpClientInterface::class);
        $mockHttpClient->setResponseFactory([
            function ($method, $url, array $options): MockResponse {
                $this->assertSame(Request::METHOD_POST, $method);
                $this->assertSame('https://api.mailjet.com/v3.1/send', $url, $url);
                $this->assertRequestBody($options['body']);

                return new MockResponse('{"Messages":[{"Status":"success","CustomID":"","To":[{"Email":"contact@an.email","MessageUUID":"","MessageID":0,"MessageHref":"https://api.mailjet.com/v3/REST/message/0"}],"Cc":[],"Bcc":[]}]}');
            },
        ]);

        $lead = $this->createLead();

        $this->em->persist($lead);
        $this->em->flush();

        $this->client->request(Request::METHOD_GET, "/s/contacts/email/{$lead->getId()}");
        $this->assertTrue($this->client->getResponse()->isOk());

        $newContent = json_decode($this->client->getResponse()->getContent(), true)['newContent'];
        $crawler    = new Crawler($newContent, $this->client->getInternalRequest()->getUri());
        $form       = $crawler->selectButton('Send')->form();
        $form->setValues(
            [
                'lead_quickemail[subject]' => 'Hello {contactfield=firstname}!',
                'lead_quickemail[body]'    => 'This is test body for {contactfield=email}!',
            ]
        );

        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isOk());

        self::assertQueuedEmailCount(1);

        $email      = self::getMailerMessage();
        $this->assertInstanceOf(MauticMessage::class, $email);
        $userHelper = self::getContainer()->get(UserHelper::class);
        $userHelper->getUser();

        $this->assertSame('Hello {contactfield=firstname}!', $email->getSubject());
        $this->assertStringContainsString('This is test body for {contactfield=email}!', (string) $email->getHtmlBody());
        $this->assertSame('This is test body for {contactfield=email}!', $email->getTextBody());
        $this->assertSame('john@doe.email', $email->getMetadata()['john@doe.email']['tokens']['{contactfield=email}']);
        $this->assertCount(1, $email->getFrom());
        $this->assertCount(1, $email->getTo());
        $this->assertSame('John', $email->getTo()[0]->getName());
        $this->assertSame($lead->getEmail(), $email->getTo()[0]->getAddress());
        $this->assertCount(1, $email->getReplyTo());
        $this->assertSame('', $email->getReplyTo()[0]->getName());
    }

    private function assertRequestBody(mixed $body): void
    {
        $bodyArray = json_decode($body, true);

        $this->assertCount(2, $bodyArray);
        $message = array_pop($bodyArray['Messages']);
        $this->assertSame('Admin User', $message['From']['Name']);
        $this->assertSame('admin@yoursite.com', $message['From']['Email']);
        $this->assertSame('John', $message['To'][0]['Name']);
        $this->assertSame('john@doe.email', $message['To'][0]['Email']);
        $this->assertSame('Hello John!', $message['Subject']);
        $this->assertSame('This is test body for john@doe.email!', $message['TextPart']);
        $this->assertEmpty($message['ReplyTo']['Name']);
        $this->assertSame('admin@mautic.test', $message['ReplyTo']['Email']);
        $this->assertEmpty($message['Attachments']);
        $this->assertArrayHasKey('CustomID', $message['Headers']);
    }
}
