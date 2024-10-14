<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Functional\Mailer\Transport;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetApiTransport;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Functional\CreateEntities;
use PHPUnit\Framework\Assert;
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

    public function testEmailDnsFieldValues(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/s/config/edit');
        $this->assertTrue($this->client->getResponse()->isOk());

        $fields = [
            'config[emailconfig][mailer_dsn][scheme]'                  => MailjetApiTransport::SCHEME,
            'config[emailconfig][mailer_dsn][host]'                    => 'default',
            'config[emailconfig][mailer_dsn][options][list][0][label]' => 'sandbox',
            'config[emailconfig][mailer_dsn][options][list][0][value]' => 'true',
        ];
        $form = $crawler->selectButton('config[buttons][save]')->form();

        $formData = $form->getValues();
        foreach ($fields as $fieldElement => $value) {
            $this->assertSame($value, $formData[$fieldElement]);
        }

        $this->assertNotEmpty($formData['config[emailconfig][mailer_dsn][password]']);
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
        $this->assertStringContainsString($expectedValidation, $crawler->text());
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
        /** @var MockHttpClient $mockHttpClient */
        $mockHttpClient = self::getContainer()->get(HttpClientInterface::class);
        $mockHttpClient->setResponseFactory([
            function ($method, $url, $options): MockResponse {
                Assert::assertSame(Request::METHOD_POST, $method);
                Assert::assertSame('https://api.mailjet.com/v3.1/send', $url, $url);
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
        $userHelper = static::getContainer()->get(UserHelper::class);
        $user       = $userHelper->getUser();

        $this->assertSame('Hello {contactfield=firstname}!', $email->getSubject());
        $this->assertStringContainsString('This is test body for {contactfield=email}!', $email->getHtmlBody());
        $this->assertSame('This is test body for {contactfield=email}!', $email->getTextBody());
        /** @phpstan-ignore-next-line */
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
