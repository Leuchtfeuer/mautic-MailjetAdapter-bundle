<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Functional\Mailer\Transport;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetApiTransport;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Functional\CreateEntities;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MailjetApiTransportEmailUsingApiTest extends MauticMysqlTestCase
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

    public function testSendEmailWithBccAddressAndAttachments(): void
    {
        /** @var MockHttpClient $mockHttpClient */
        $mockHttpClient = self::getContainer()->get(HttpClientInterface::class);
        $mockHttpClient->setResponseFactory([
            function ($method, $url, $options): MockResponse {
                Assert::assertSame(Request::METHOD_POST, $method);
                Assert::assertSame('https://api.mailjet.com/v3.1/send', $url);
                $this->assertRequestBody($options['body']);

                return new MockResponse('{"Messages":[{"Status":"success","CustomID":"","To":[{"Email":"contact@an.email","MessageUUID":"","MessageID":0,"MessageHref":"https://api.mailjet.com/v3/REST/message/0"}],"Cc":[],"Bcc":[]}]}');
            },
        ]);

        $lead    = $this->createLead();
        $segment = $this->createSegment();
        $asset   = $this->getAsset();
        $email   = $this->createEmail($segment, $asset);

        $this->addContactToSegment($lead, $segment);

        $this->em->flush();

        $this->client->request(Request::METHOD_POST, '/s/ajax?action=email:sendBatch', [
            'id'         => $email->getId(),
            'pending'    => 1,
            'batchLimit' => 10,
        ]);

        self::assertQueuedEmailCount(1);

        $email = self::getMailerMessage();

        $this->assertSame('Hello {contactfield=firstname}!', $email->getSubject());
        $this->assertStringContainsString('This is test body for {contactfield=email}!', $email->getHtmlBody());
        $this->assertSame('This is test body for {contactfield=email}!', $email->getTextBody());
        /** @phpstan-ignore-next-line */
        $this->assertSame('john@doe.email', $email->getMetadata()['john@doe.email']['tokens']['{contactfield=email}']);
        $this->assertCount(1, $email->getFrom());
        $this->assertSame('hello@doe.com', $email->getFrom()[0]->getAddress());
        $this->assertCount(1, $email->getTo());
        $this->assertSame('John', $email->getTo()[0]->getName());
        $this->assertSame($lead->getEmail(), $email->getTo()[0]->getAddress());
        $this->assertCount(1, $email->getReplyTo());
        $this->assertSame('', $email->getReplyTo()[0]->getName());
        $this->assertEmpty($email->getBcc()[0]->getName());
        $this->assertSame('visibility@doe.com', $email->getBcc()[0]->getAddress());
    }

    private function assertRequestBody(mixed $body): void
    {
        $bodyArray = json_decode($body, true);

        $this->assertCount(2, $bodyArray);
        $message = array_pop($bodyArray['Messages']);
        $this->assertEmpty($message['From']['Name']);
        $this->assertSame('hello@doe.com', $message['From']['Email']);
        $this->assertSame('John', $message['To'][0]['Name']);
        $this->assertSame('john@doe.email', $message['To'][0]['Email']);
        $this->assertSame('Hello {contactfield=firstname}!', $message['Subject']);
        $this->assertSame('This is test body for {contactfield=email}!', $message['TextPart']);
        $this->assertStringContainsString('This is test body for {contactfield=email}!<img height="1" width="1"', $message['HTMLPart']);
        $this->assertEmpty($message['ReplyTo']['Name']);
        $this->assertSame('reply@doe.com', $message['ReplyTo']['Email']);
        $this->assertSame('visibility@doe.com', $message['Bcc'][0]['Email']);
        $this->assertCount(1, $message['Attachments']);
        $this->assertArrayHasKey('CustomID', $message['Headers']);
    }
}
