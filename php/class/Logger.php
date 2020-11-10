<?php

declare(strict_types=1);

namespace Orms;

use \DateTime;
use Orms\Config;
use PDOException;

class Logger
{
    /**
     *
     * @param string $clientNumber
     * @param string $serviceNumber
     * @param string $messageId
     * @param string $service
     * @param string $action
     * @param string $message
     * @param DateTime $timestamp
     * @param string $result
     * @return void
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
    ): void {
        $dbh = Config::getDatabaseConnection("LOGS");
        $query = $dbh->prepare("
            REPLACE INTO SmsLog(
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
