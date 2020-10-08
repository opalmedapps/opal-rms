<?php declare(strict_types = 1);

namespace Orms\Sms;

use DateTime;
use Exception;
use Orms\Config;
use Orms\Sms\{SmsTwilio,SmsCdyne};
use Orms\ArrayUtil;
use Orms\Logger;

SmsInterface::__init();

class SmsInterface
{
    private static array $availableNumbers = [];

    static function __init(): void
    {
        $smsConfigs = Config::getConfigs("sms");
        $twilioConfigs = Config::getConfigs("twilio");
        $cdyneConfigs = Config::getConfigs("cdyne");

        if($smsConfigs["enabled"] !== "1") {
            throw new Exception("Sms not enabled");
        }

        self::$availableNumbers["Twilio"] = $twilioConfigs["REGISTERED_LONG_CODES"];
        self::$availableNumbers["Cdyne"] = $cdyneConfigs["REGISTERED_LONG_CODES"];
    }

    static function sendSms(string $clientNumber,string $message,string $serviceNumber = NULL): bool
    {
        #by default, we send sms using Twilio
        if($serviceNumber === NULL) {
            $serviceNumber = self::$availableNumbers["Twilio"][array_rand(self::$availableNumbers["Twilio"])];
        }

        $provider = NULL;
        $messageId = NULL;

        if(in_array($serviceNumber,self::$availableNumbers["Twilio"],TRUE))
        {
            $provider = "Twilio";
            try {
                $messageId = SmsTwilio::sendSms($clientNumber,$message,$serviceNumber);
            }
            catch(Exception $e) {}

        }
        elseif(in_array($serviceNumber,self::$availableNumbers["Cdyne"],TRUE))
        {
            $provider = "Cdyne";
            try {
                $messageId = SmsCdyne::sendSms($clientNumber,$message,$serviceNumber);
            }
            catch(Exception $e) {}
        }

        if($provider === NULL) {
            throw new Exception("Unknown sms provider");
        }

        Logger::LogSms(
            $clientNumber,
            $serviceNumber,
            $messageId,
            $provider,
            "SENT",
            $message,
            new DateTime(),
            $messageId ? "SUCCESS" : "FAILURE"
        );

        return $messageId ? TRUE : FALSE;
    }

    /**
     * @return array<SmsReceivedMessage>
     */
    static function getReceivedMessages(DateTime $timestamp): array
    {
        $messages = [];

        $messages = array_merge($messages,SmsTwilio::getReceivedMessages($timestamp));
        $messages = array_merge($messages,SmsCdyne::getReceivedMessages());

        usort($messages,function($a,$b) {
            return $a->timeReceived <=> $b->timeReceived;
        });

        foreach($messages as $x) {
            Logger::LogSms(
                $x->clientNumber,
                $x->serviceNumber,
                $x->messageId,
                $x->provider,
                "RECEIVED",
                $x->body,
                $x->timeReceived,
                "SUCCESS"
            );
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
