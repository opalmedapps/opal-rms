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
        $this->messageId    = $messageId;
        $this->body         = $body;
        $this->fromNumber   = $fromNumber;
        $this->toNumber     = $toNumber;
        $this->timeReceived = $timeReceived;
    }
}

?>
