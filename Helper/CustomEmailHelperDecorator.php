<?php

namespace MauticPlugin\LeuchtfeuerMailjetAdapterBundle\Helper;

use Mautic\EmailBundle\Helper\MailHelper;

class CustomEmailHelperDecorator extends MailHelper
{
    /**
     * If batching is supported and enabled, the message will be queued and will on be sent upon flushQueue().
     * Otherwise, the message will be sent to the transport immediately.
     *
     * @param bool   $dispatchSendEvent
     * @param string $returnMode        What should happen post send/queue to $this->message after the email send is attempted.
     *                                  Options are:
     *                                  RESET_TO           resets the to recipients and resets errors
     *                                  FULL_RESET         creates a new MauticMessage instance and resets errors
     *                                  DO_NOTHING         leaves the current errors array and MauticMessage instance intact
     *                                  NOTHING_IF_FAILED  leaves the current errors array MauticMessage instance intact if it fails, otherwise reset_to
     *                                  RETURN_ERROR       return an array of [success, $errors]; only one applicable if message is queued
     */
    public function queue($dispatchSendEvent = false, $returnMode = self::QUEUE_RESET_TO): bool|array
    {
        $key = key($this->metadata);
        if (!empty($key)) {
            foreach ($this->queuedRecipients as $address => $name) {
                if (isset($this->metadata[$key]['contacts'][$address])) {
                    $newAddress = $this->getNewUniqueEmail($address, $key);
                    unset($this->queuedRecipients[$address]);
                    $this->queuedRecipients[$newAddress] = $name;
                }
            }
        }

        return parent::queue($dispatchSendEvent, $returnMode);
    }

    public function send($dispatchSendEvent = false, $isQueueFlush = false): bool|array
    {
        $key = key($this->metadata);
        if (!empty($key)) {
            foreach ($this->queuedRecipients as $address => $name) {
                if (isset($this->metadata[$key]['contacts'][$address])) {
                    $newAddress = $this->getNewUniqueEmail($address, $key);
                    unset($this->queuedRecipients[$address]);
                    $this->queuedRecipients[$newAddress] = $name;
                }
            }
        }

        return parent::send($dispatchSendEvent, $isQueueFlush);
    }

    /**
     * @param string $address
     * @param string $fromAddress
     */
    public function getNewUniqueEmail($address, $fromAddress): string
    {
        if (!isset($this->metadata[$fromAddress]['contacts'][$address])) {
            return $address;
        }

        $i    =1;
        $loop = true;
        while ($loop) {
            if (!isset($this->metadata[$fromAddress]['contacts'][$address.'+'.$i])) {
                $loop = false;

                return $address.'+'.$i;
            }
            ++$i;
        }

        return $address;
    }
}
