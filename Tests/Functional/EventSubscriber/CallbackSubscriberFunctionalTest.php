<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Tests\Functional\EventSubscriber;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\EventSubscriber\CallbackSubscriber;
use MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport\MailjetSmtpTransport;
use Symfony\Component\HttpFoundation\Request;

final class CallbackSubscriberFunctionalTest extends MauticMysqlTestCase
{
    protected function setUp(): void
    {
        if ('testMailjetTransportWhenNoEmailDsnConfigured' !== $this->getName()) {
            $this->configParams['mailer_dsn'] = MailjetSmtpTransport::SCHEME.'://user:pass@host:25';
        }

        parent::setUp();
    }

    public function testGetSubscribedEvents(): void
    {
        $this->assertEquals(
            [
                EmailEvents::ON_TRANSPORT_WEBHOOK => 'onTransportWebhookCallbackRequest',
            ],
            CallbackSubscriber::getSubscribedEvents()
        );
    }

    public function testMailjetTransportWhenNoEmailDsnConfigured(): void
    {
        $this->client->request(Request::METHOD_POST, '/mailer/callback');
        $response = $this->client->getResponse();

        $this->assertSame('No email transport that could process this callback was found', $response->getContent());
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testMailjetTransportReceivesEmptyPayload(): void
    {
        $this->client->request(Request::METHOD_POST, '/mailer/callback');
        $response = $this->client->getResponse();

        $this->assertSame('There is no data to process.', $response->getContent());
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testMailjetTransportCallbackWithOneBouncePayload(): void
    {
        $type    = 'bounce';
        $email   = $type.'@mautic.test';
        $contact = $this->createContact($email);
        $hash    = '1123asda13';
        $stat    = $this->createStat($contact, $email, $hash);

        $this->em->flush();

        $param                = $this->payloadStructure($type, $email, $hash);
        $param['hard_bounce'] = true;

        $this->client->request(Request::METHOD_POST, '/mailer/callback', $param);
        $response = $this->client->getResponse();

        $this->assertSame('Callback processed', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());

        $result = [
            'comments' => 'HARD: bounce: bounce',
            'reason'   => DoNotContact::BOUNCED,
        ];

        $openDetails = $stat->getOpenDetails();
        $bounces     = $openDetails['bounces'][0];
        $this->assertSame($result['comments'], $bounces['reason']);

        $this->assertDoNotContact($contact, $result);
    }

    public function testCallbackProcessByHashId(): void
    {
        $payload  = [];
        $stats    = [];
        $contacts = [];
        foreach (['bounce', 'blocked', 'spam', 'unsub', 'sent'] as $type) {
            $email   = $type.'@mautic.test';
            $hash    = '123'.$type.'sasd';
            $contact = $this->createContact($email);

            $contacts[$type] = $contact;
            $stats[$email]   = $this->createStat($contact, $email, $hash);
            $payload[]       = $this->payloadStructure($type, $email, $hash);
        }

        $this->em->flush();

        $this->client->request(Request::METHOD_POST, '/mailer/callback', $payload);
        $response = $this->client->getResponse();

        $this->assertSame('Callback processed', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());

        foreach (['bounce', 'blocked', 'spam', 'unsub'] as $type) {
            $result  = $this->getCommentAndReason($type);
            $contact = $contacts[$type];

            $openDetails = $stats[$contact->getEmail()]->getOpenDetails();
            $bounces     = $openDetails['bounces'][0];

            $this->assertSame($result['comments'], $bounces['reason']);

            if ($type ==='bounce'){
                $this->assertSoftBounceDoNotContact($contact, $result);
            } else {
                $this->assertDoNotContact($contact, $result);
            }

            //$this->assertDoNotContact($contact, $result);
        }
    }

    public function testCallbackProcessByAddresses(): void
    {
        $payload  = [];
        $contacts = [];
        foreach (['bounce', 'blocked', 'spam', 'unsub'] as $type) {
            $email           = $type.'@mautic.test';
            $contact         = $this->createContact($email);
            $contacts[$type] = $contact;
            $payload[]       = $this->payloadStructure($type, $email);
        }

        $this->em->flush();

        $this->client->request(Request::METHOD_POST, '/mailer/callback', $payload);
        $response = $this->client->getResponse();

        $this->assertSame('Callback processed', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());

        foreach (['bounce', 'blocked', 'spam', 'unsub'] as $type) {
            $result = $this->getCommentAndReason($type);

            if ($type ==='bounce'){
                $this->assertSoftBounceDoNotContact($contacts[$type], $result);
            } else {
                $this->assertDoNotContact($contacts[$type], $result);
            }

            //$this->assertDoNotContact($contacts[$type], $result);
        }
    }

    /**
     * @return array<string, int|string>
     */
    private function payloadStructure(string $type, string $email, string $hash = ''): array
    {
        $returnArray = [
            'event'            => $type,
            'time'             => '1513975381',
            'MessageID'        => 0,
            'Message_GUID'     => 0,
            'email'            => $email,
            'mj_campaign_id'   => 0,
            'mj_contact_id'    => 0,
            'customcampaign'   => '',
            'mj_message_id'    => 0,
            'CustomID'         => !empty($hash) ? $hash.'-'.$email : $email,
            'Payload'          => '',
            'error_related_to' => $type,
            'error'            => $type,
            'source'           => 'spam button',
        ];

        if ('bounce' === $type) {
            $returnArray['hard_bounce'] = 'false';
        }

        return $returnArray;
    }

    /**
     * @return array<string, string|int>
     */
    private function getCommentAndReason(string $type): array
    {
        return match ($type) {
            'blocked' => [
                'comments' => 'BLOCKED: '.$type.': '.$type,
                'reason'   => DoNotContact::BOUNCED,
            ],
            'bounce' => [
                'comments' => 'SOFT: '.$type.': '.$type,
                'reason'   => DoNotContact::BOUNCED,
            ],
            'spam' => [
                'comments' => 'User reported email as spam, source: spam button',
                'reason'   => DoNotContact::UNSUBSCRIBED,
            ],
            'unsub' => [
                'comments' => 'User unsubscribed',
                'reason'   => DoNotContact::UNSUBSCRIBED,
            ],
            default => [
                'comments' => '',
                'reason'   => '',
            ],
        };
    }

    private function createContact(string $email): Lead
    {
        $lead = new Lead();
        $lead->setEmail($email);

        $this->em->persist($lead);

        return $lead;
    }

    private function createStat(Lead $contact, string $emailAddress, string $trackingHash): Stat
    {
        $stat = new Stat();
        $stat->setLead($contact);
        $stat->setTrackingHash($trackingHash);
        $stat->setEmailAddress($emailAddress);
        $stat->setDateSent(new \DateTime());

        $this->em->persist($stat);

        return $stat;
    }

    /**
     * @param array<string, string|int> $result
     */
    private function assertDoNotContact(Lead $contact, array $result): void
    {
        $dnc = $contact->getDoNotContact()->current();

        $this->assertSame('email', $dnc->getChannel());
        $this->assertSame($result['comments'], $dnc->getComments());
        $this->assertSame($contact, $dnc->getLead());
        $this->assertSame($result['reason'], $dnc->getReason());
    }

    private function assertSoftBounceDoNotContact(Lead $contact, array $result):void
    {
        $dnc = $contact->getDoNotContact()->current();

        $this->assertSame('mailjet', $dnc->getChannel());
        $this->assertSame($result['comments'], $dnc->getComments());
        $this->assertSame($contact, $dnc->getLead());
        $this->assertSame($result['reason'], $dnc->getReason());
    }
}
