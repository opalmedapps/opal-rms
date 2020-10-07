<?php declare(strict_types = 1);

namespace Orms;

use DateTime;

class SmsReceivedMessage
{
    public string $messageId;
    public string $body;
    public string $fromNumber;
    public string $toNumber;
    public DateTime $timeReceived;

    function __construct(string $messageId,
                         string $body,
                         string $fromNumber,
                         string $toNumber,
                         DateTime $timeReceived)
    {
        #remove international code and plus sign
        $fromNumber = preg_replace("/^\+1/","",$fromNumber);
        $toNumber = preg_replace("/^\+1/","",$toNumber);

        $this->messageId    = $messageId;
        $this->body         = $body;
        $this->fromNumber   = $fromNumber;
        $this->toNumber     = $toNumber;
        $this->timeReceived = $timeReceived;
    }
}

?>
