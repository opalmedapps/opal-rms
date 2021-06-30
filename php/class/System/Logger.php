<?php declare(strict_types=1);

namespace Orms\System;

use \DateTime;
use Orms\DataAccess\Database;
use PDOException;

class Logger
{
    /**
     *
     * @throws PDOException
     */
    static function LogSms(
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
        $dbh = Database::getLogsConnection();
        $query = $dbh->prepare("
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
        ");
        $query->execute([
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
