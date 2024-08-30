<?php

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport;

use Mautic\EmailBundle\Model\EmailStatModel;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\MonitoredEmail\Search\ContactFinder;
use Mautic\LeadBundle\Entity\DoNotContact as DNC;
use Mautic\LeadBundle\Model\DoNotContact;

class MailjetTransportCallback extends TransportCallback{
    public function __construct(
        private DoNotContact $dncModel,
        private ContactFinder $finder,
        private EmailStatModel $emailStatModel
    ) {
        parent::__construct($dncModel, $finder, $emailStatModel);
    }

    public function addFailureByHashId($hashId, $comments, $dncReason = DNC::BOUNCED): void
    {
        $result = $this->finder->findByHash($hashId);

        if ($contacts = $result->getContacts()) {
            $stat = $result->getStat();
            $this->updateStatDetails($stat, $comments, $dncReason);

            $email   = $stat->getEmail();
            foreach ($contacts as $contact) {
                if (str_starts_with($comments, 'SOFT')) {
                    $channel = 'mailjet';
                } else {
                    $channel = ($email) ? ['email' => $email->getId()] : 'email';
                }
                $this->dncModel->addDncForContact($contact->getId(), $channel, $dncReason, $comments);
            }
        }
    }

    public function addFailureByAddress($address, $comments, $dncReason = DNC::BOUNCED, $channelId = null): void
    {
        $result = $this->finder->findByAddress($address);

        if ($contacts = $result->getContacts()) {
            foreach ($contacts as $contact) {
                if (str_starts_with($comments, 'SOFT')) {
                    $channel = 'mailjet';
                } else {
                    $channel = ($channelId) ? ['email' => $channelId] : 'email';
                }
                $this->dncModel->addDncForContact($contact->getId(), $channel, $dncReason, $comments);
            }
        }
    }
    /**
     * @param int      $dncReason
     * @param int|null $channelId
     */
    public function addFailureByContactId($id, $comments, $dncReason = DNC::BOUNCED, $channelId = null): void
    {
        if (str_starts_with($comments, 'SOFT')) {
            $channel = 'mailjet';
        } else {
            $channel = ($channelId) ? ['email' => $channelId] : 'email';
        }
        $this->dncModel->addDncForContact($id, $channel, $dncReason, $comments);
    }

    private function updateStatDetails(Stat $stat, $comments, $dncReason): void
    {
        if (DNC::BOUNCED === $dncReason) {
            $stat->setIsFailed(true);
        }

        $openDetails = $stat->getOpenDetails();
        if (!isset($openDetails['bounces'])) {
            $openDetails['bounces'] = [];
        }
        $dtHelper                 = new DateTimeHelper();
        $openDetails['bounces'][] = [
            'datetime' => $dtHelper->toUtcString(),
            'reason'   => $comments,
        ];
        $stat->setOpenDetails($openDetails);
        $this->emailStatModel->saveEntity($stat);
    }
}