<?php declare(strict_types=1);

namespace Orms\DataAccess;

use \DateTime;
use Orms\DataAccess\Database;

class LoggerAccess
{
    static function insertSmsEvent(
        string $clientNumber,
        string $serviceNumber,
        string $messageId,
        string $service,
        string $action,
        string $message,
        DateTime $timestamp,
        string $result
    ): void
    {
        Database::getLogsConnection()->prepare("
            INSERT INTO SmsLog(
                SmsTimestamp
                ,Result
                ,Action
                ,Service
                ,MessageId
                ,ServicePhoneNumber
                ,ClientPhoneNumber
                ,Message
            )
            VALUES(
                :timestamp
                ,:result
                ,:action
                ,:service
                ,:messageId
                ,:serviceNum
                ,:clientNum
                ,:message
            )
        ")->execute([
            ":timestamp"   => $timestamp->format("Y-m-d H:i:s"),
            ":result"      => $result,
            ":action"      => $action,
            ":service"     => $service,
            ":messageId"   => $messageId,
            ":serviceNum"  => $serviceNumber,
            ":clientNum"   => $clientNumber,
            ":message"     => $message
        ]);
    }
}
