<?php declare(strict_types = 1);

namespace Orms\Sms\Internal;

use DateTime;

interface SmsClassInterface
{
    function sendSms(string $clientNumber,string $serviceNumber,string $message): string;

    /**
     * @param string[] $availableNumbers
     * @return SmsReceivedMessage[]
     */
    function getReceivedMessages(array $availableNumbers,DateTime $timestamp): array;
}
