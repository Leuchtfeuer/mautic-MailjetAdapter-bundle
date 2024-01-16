<?php

declare(strict_types=1);

namespace MauticPlugin\MailjetBundle\EventSubscriber;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\TransportWebhookEvent;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\LeadBundle\Entity\DoNotContact;
use MauticPlugin\MailjetBundle\Mailer\Transport\MailjetApiTransport;
use MauticPlugin\MailjetBundle\Mailer\Transport\MailjetSmtpTransport;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Transport\Dsn;

class CallbackSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TransportCallback $transportCallback,
        private CoreParametersHelper $coreParametersHelper
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::ON_TRANSPORT_WEBHOOK => 'onTransportWebhookCallbackRequest',
        ];
    }

    public function onTransportWebhookCallbackRequest(TransportWebhookEvent $webhookEvent): void
    {
        $dsn = Dsn::fromString($this->coreParametersHelper->get('mailer_dsn'));

        if (!in_array($dsn->getScheme(), [MailjetApiTransport::SCHEME, MailjetSmtpTransport::MAUTIC_MAILJET_SMTP_SCHEME])) {
            return;
        }

        $postData = $webhookEvent->getRequest()->request->all();
        if (empty($postData)) {
            $webhookEvent->setResponse(new Response('There is no data to process.', Response::HTTP_NOT_FOUND));

            return;
        }

        if (isset($postData['event'])) {
            $events = [
                $postData,
            ];
        } else {
            $events = $postData;
        }

        foreach ($events as $event) {
            if ('bounce' === $event['event'] || 'blocked' === $event['event']) {
                $reason = $event['error_related_to'].': '.$event['error'];
                $type   = DoNotContact::BOUNCED;
            } elseif ('spam' === $event['event']) {
                $reason = 'User reported email as spam, source: '.$event['source'];
                $type   = DoNotContact::UNSUBSCRIBED;
            } elseif ('unsub' === $event['event']) {
                $reason = 'User unsubscribed';
                $type   = DoNotContact::UNSUBSCRIBED;
            } else {
                continue;
            }

            if (isset($event['CustomID']) && '' !== $event['CustomID'] && false !== strpos($event['CustomID'], '-', 0)) {
                $fistDashPos = strpos($event['CustomID'], '-', 0);
                $leadIdHash  = substr($event['CustomID'], 0, $fistDashPos);
                $leadEmail   = substr($event['CustomID'], $fistDashPos + 1, strlen($event['CustomID']));
                if ($event['email'] == $leadEmail) {
                    $this->transportCallback->addFailureByHashId($leadIdHash, $reason, $type);
                }
            } else {
                $this->transportCallback->addFailureByAddress($event['email'], $reason, $type);
            }
        }

        $webhookEvent->setResponse(new Response('Callback processed'));
    }
}
