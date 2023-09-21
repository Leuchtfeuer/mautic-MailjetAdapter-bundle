<?php

declare(strict_types=1);

namespace MauticPlugin\MailjetBundle\Tests\Unit\EventSubscriber;

use Mautic\EmailBundle\EmailEvents;
use MauticPlugin\MailjetBundle\EventSubscriber\CallbackSubscriber;
use PHPUnit\Framework\TestCase;

final class CallbackSubscriberFunctionalTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $this->assertEquals(
            [
                EmailEvents::ON_TRANSPORT_WEBHOOK => 'onTransportWebhookCallbackRequest',
            ],
            CallbackSubscriber::getSubscribedEvents()
        );
    }
}
