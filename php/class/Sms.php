<?php declare(strict_types = 1);

namespace Orms;

use DateTime;
use Exception;
use Orms\Config;
use Orms\SmsReceivedMessage;
use Orms\ArrayUtil;
use Orms\Logger;
use Twilio\Rest\Client;

Sms::__init();

class Sms
{
    private static ?string $username = NULL;
    private static ?string $password = NULL;
    private static array $availableNumbers = [];

    static function __init()
    {
        $configs = Config::getConfigs("sms");
        self::$username = $configs["SMS_LICENCE_KEY"];
        self::$password = $configs["SMS_TOKEN"];
        self::$availableNumbers = $configs["REGISTERED_LONG_CODES"];
    }

    static function sendSms(string $toNumber,string $message,string $fromNumber = NULL): bool
    {
        if($fromNumber === NULL) {
            $fromNumber = self::$availableNumbers[array_rand(self::$availableNumbers)];
        }

        try {
            $twilio = new Client(self::$username,self::$password);
            $sentSms = $twilio->messages->create($toNumber,[
                "body" => $message,
                "from" => $fromNumber
            ]);

            $messageId = $sentSms->sid;
            $result = "SUCCESS";
        }
        catch(\Exception $e) {
            $messageId = NULL;
            $result = "FAILURE";
        }

        Logger::LogSms(
            $toNumber,
            $fromNumber,
            $messageId,
            "Twilio",
            "SENT",
            $message,
            new DateTime(),
            $result
        );

        return ($result === "SUCCESS");
    }

    static function getReceivedMessages(DateTime $timestamp): array
    {
        try {
            $twilio = new Client(self::$username,self::$password);

            $messages = [];
            foreach(self::$availableNumbers as $number)
            {
                $incomingMessages = $twilio->messages->read([
                    "to"        => $number,
                    "dateSent"  => $timestamp
                ]);
                $messages = array_merge($messages,$incomingMessages);
            }
            $messages = array_filter($messages,function($x) {
                return $x->direction === "inbound";
            });

            $messages = array_map(function($x) {
                return new SmsReceivedMessage(
                    $x->sid,
                    $x->body,
                    $x->from,
                    $x->to,
                    $x->dateSent->setTimezone((new DateTime)->getTimezone())
                );
            },$messages);

            usort($messages,function($a,$b) {
                return $a->timeReceived <=> $b->timeReceived;
            });

            foreach($messages as $x) {
                Logger::LogSms(
                    $x->fromNumber,
                    $x->toNumber,
                    $x->messageId,
                    "Twilio",
                    "RECEIVED",
                    $x->body,
                    $x->timeReceived,
                    "SUCCESS"
                );
            }
        }
        catch(\Exception $e) {
            $messages = [];
        }

        return $messages;
    }

    /* messages are classified by speciality, type, and event:
        speciality is the speciality group the message is used in
        type is subcategory of the speciality group and is used to link the appointment code to a message
        event indicates when the message should be sent out (during check in, as a reminder, etc)
    */
    static function getPossibleSmsMessages(): array
    {
        $dbh = Config::getDatabaseConnection("ORMS");
        $query = $dbh->prepare("
            SELECT
                Speciality
                ,Type
                ,Event
                ,Language
                ,Message
            FROM
                SmsMessage
            ORDER BY
                Speciality,Type,Event,Language
        ");
        $query->execute();

        $messages = $query->fetchAll();
        $messages = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($messages,"Speciality","Type","Event","Language");
        $messages = ArrayUtil::convertSingleElementArraysRecursive($messages);

        return utf8_encode_recursive($messages);
    }

}

?>
