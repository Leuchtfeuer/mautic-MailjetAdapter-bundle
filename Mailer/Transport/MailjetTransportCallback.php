<?php

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Mailer\Transport;

use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Model\EmailStatModel;
use Mautic\EmailBundle\Model\TransportCallback;
use Mautic\EmailBundle\MonitoredEmail\Search\ContactFinder;
use Mautic\LeadBundle\Entity\DoNotContact as DNC;
use Mautic\LeadBundle\Model\DoNotContact;

class MailjetTransportCallback extends TransportCallback
{
    public function __construct(
        private DoNotContact $dncModel,
        private ContactFinder $finder,
        private EmailStatModel $emailStatModel
    ) {
        parent::__construct($dncModel, $finder, $emailStatModel);
    }

    /**
     * @param string $hashId
     * @param string $comments
     * @param int    $dncReason
     */
    public function addFailureByHashId($hashId, $comments, $dncReason = DNC::BOUNCED): void
    {
        $result = $this->finder->findByHash($hashId);

        if ($contacts = $result->getContacts()) {
            $stat = $result->getStat();
            $this->updateStatDetails($stat, $comments, $dncReason);

            $email   = $stat->getEmail();
            foreach ($contacts as $contact) {
                $channel = $this->getChannelForHashId($comments, $email);
                $this->dncModel->addDncForContact($contact->getId(), $channel, $dncReason, $comments);
            }
        }
    }

    public function addFailureByAddress($address, $comments, $dncReason = DNC::BOUNCED, $channelId = null): void
    {
        $result = $this->finder->findByAddress($address);

        if ($contacts = $result->getContacts()) {
            foreach ($contacts as $contact) {
                $channel = $this->getChannelForAddressOrContact($comments, $channelId);
                $this->dncModel->addDncForContact($contact->getId(), $channel, $dncReason, $comments);
            }
        }
    }

    /**
     * @param int      $dncReason
     * @param int|null $channelId
     * @param string   $comments
     * @param int      $id
     */
    public function addFailureByContactId($id, $comments, $dncReason = DNC::BOUNCED, $channelId = null): void
    {
        $channel = $this->getChannelForAddressOrContact($comments, $channelId);
        $this->dncModel->addDncForContact($id, $channel, $dncReason, $comments);
    }

    /**
     * @return array<string, int>|string
     */
    private function getChannelForAddressOrContact(string $comments, ?int $channelId): array|string
    {
        if (str_starts_with($comments, 'SOFT')) {
            return 'mailjet';
        }

        $mailArray = [
            'email' => $channelId,
        ];

        return $channelId ? $mailArray : 'email';
    }

    /**
     * @param string $comments
     * @param Email  $email
     *
     * @return array<string, int>|string
     */
    private function getChannelForHashId($comments, $email): array|string
    {
        if (str_starts_with($comments, 'SOFT')) {
            return 'mailjet';
        }

        if (null == $email) {
            return 'email';
        }

        return [
            'email' => $email->getId(),
        ];
    }

    private function updateStatDetails(Stat $stat, string $comments, int $dncReason): void
    {
        if (DNC::BOUNCED === $dncReason) {
            $stat->setIsFailed(true);
        }

        $openDetails = $stat->getOpenDetails() ?: [];
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
