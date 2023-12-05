<?php

declare(strict_types=1);

namespace MauticPlugin\MailjetBundle\Tests\Functional;

use Mautic\AssetBundle\Entity\Asset;
use Mautic\EmailBundle\Entity\Email;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;

trait CreateEntities
{
    private function createLead(): Lead
    {
        $lead = new Lead();
        $lead->setEmail('john@doe.email')->setFirstname('John');

        $this->em->persist($lead);

        return $lead;
    }

    private function createSegment(): LeadList
    {
        $segment = new LeadList();
        $segment->setName('Test Segment A');
        $segment->setPublicName('Test Segment A');
        $segment->setAlias('test-segment-a');

        $this->em->persist($segment);

        return $segment;
    }

    private function addContactToSegment(Lead $lead, LeadList $segment): void
    {
        $listLead = new ListLead();
        $listLead->setLead($lead);
        $listLead->setList($segment);
        $listLead->setDateAdded(new \DateTime());

        $this->em->persist($listLead);
        $this->em->flush();
    }

    private function getAsset(): Asset
    {
        $uploadDir = self::getContainer()->get('mautic.helper.core_parameters')->get('upload_dir') ?? sys_get_temp_dir();
        $tmpFile   = tempnam($uploadDir, 'mautic_asset_email_test_').'.txt';
        $file      = fopen($tmpFile, 'w');

        fwrite($file, 'some text here');
        fclose($file);

        $asset = new Asset();
        $asset->setTitle('Email Attachment');
        $asset->setAlias('EmailTest');
        $asset->setDateAdded(new \DateTime());
        $asset->setDateModified(new \DateTime());
        $asset->setCreatedByUser('User');
        $asset->setStorageLocation('local');
        $asset->setPath(basename($tmpFile));
        $asset->setExtension('txt');

        $this->em->persist($asset);

        return $asset;
    }

    private function createEmail(LeadList $segment, Asset $asset): Email
    {
        $email = new Email();
        $email->setName('Send Test');
        $email->setTemplate('blank');
        $email->setEmailType('list');
        $email->addList($segment);
        $email->setSubject('Hello {contactfield=firstname}!');
        $email->setCustomHtml('This is test body for {contactfield=email}!');
        $email->setFromAddress('hello@doe.com');
        $email->setReplyToAddress('reply@doe.com');
        $email->setBccAddress('visibility@doe.com');
        $email->addAssetAttachment($asset);

        $this->em->persist($email);

        return $email;
    }
}
