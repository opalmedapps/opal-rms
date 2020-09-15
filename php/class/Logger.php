<?php declare(strict_types = 1);

namespace ORMS;

use \DateTime;
use ORMS\Config;

class Logger
{
    #logs sent or received sms
    static function LogSms(string $clientNumber
                          ,string $serviceNumber
                          ,?string $messageId
                          ,string $service
                          ,string $action
                          ,string $message
                          ,DateTime $timestamp
                          ,string $result
    ): void
    {
        $dbh = Config::getDatabaseConnection("LOGS");
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
            ":timestamp"   => $timestamp->getTimestamp(),
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
