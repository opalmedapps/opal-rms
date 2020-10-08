<?php declare(strict_types = 1);

namespace Orms\Sms;

use DateTime;

class SmsReceivedMessage
{
    public string $messageId;
    public string $body;
    public string $clientNumber;
    public string $serviceNumber;
    public DateTime $timeReceived;
    public string $provider;

    function __construct(string $messageId,
                         string $body,
                         string $clientNumber,
                         string $serviceNumber,
                         DateTime $timeReceived,
                         string $provider)
    {
        $this->messageId        = $messageId;
        $this->body             = $body;
        $this->clientNumber     = $clientNumber;
        $this->serviceNumber    = $serviceNumber;
        $this->timeReceived     = $timeReceived;
        $this->provider         = $provider;
    }
}

?>
