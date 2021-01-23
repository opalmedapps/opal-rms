<?php declare(strict_types = 1);

namespace Orms;

use DateTime;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

use Orms\Config;
use Orms\Sms\{SmsTwilio,SmsCdyne,SmsReceivedMessage};
use Orms\ArrayUtil;
use Orms\Logger;
use PDOException;

SmsInterface::__init();

class SmsInterface
{
    /**
     * @var array<string[]>
     */
    private static array $availableNumbers = [];

    /**
     *
     * @throws Exception
     */
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

    /**
     *
     * @param string|null $serviceNumber
     * @throws GuzzleException
     * @throws PDOException
     */
    static function sendSms(string $clientNumber,string $message,string $serviceNumber = NULL): void
    {
        #by default, we send sms using Twilio
        if($serviceNumber === NULL) {
            $serviceNumber = self::$availableNumbers["Twilio"][array_rand(self::$availableNumbers["Twilio"])];
        }

        $provider = "UNKNOWN";
        $messageId = NULL;
        $result = NULL;

        try
        {
            if(in_array($serviceNumber,self::$availableNumbers["Twilio"],TRUE))
            {
                $provider = "Twilio";
                $messageId = SmsTwilio::sendSms($clientNumber,$message,$serviceNumber);
            }
            elseif(in_array($serviceNumber,self::$availableNumbers["Cdyne"],TRUE))
            {
                $provider = "Cdyne";
                $messageId = SmsCdyne::sendSms($clientNumber,$message,$serviceNumber);
            }
            else {
                throw new Exception("Unknown sms provider");
            }

            $result = "SUCCESS";
        }
        catch(Exception $e)
        {
            $messageId = "ORMS_sms_" . (new DateTime())->getTimestamp() . "_" . rand();
            $result = "FAILURE : {$e->getMessage()}";
        }

        #it might be possible to generate no message id when sending an sms (cdyne) so we have to generate one
        if($messageId === NULL)
        {
            $messageId = "ORMS_sms_" . (new DateTime())->getTimestamp() . "_" . rand();
            $result = "FAILURE : Couldn't generate a messageId";
        }

        Logger::LogSms(
            $clientNumber,
            $serviceNumber,
            $messageId,
            $provider,
            "SENT",
            $message,
            new DateTime(),
            $result
        );
    }

    /**
     *
     * @return SmsReceivedMessage[]
     * @throws GuzzleException
     * @throws PDOException
     */
    static function getReceivedMessages(DateTime $timestamp): array
    {
        $messages = [];

        $messages = array_merge($messages,SmsTwilio::getReceivedMessages($timestamp));
        $messages = array_merge($messages,SmsCdyne::getReceivedMessages());

        usort($messages,function(SmsReceivedMessage $a,SmsReceivedMessage $b) {
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

    /**
     *  Messages are classified by speciality, type, and event:
     *  * speciality is the speciality group the message is used in
     *  * type is subcategory of the speciality group and is used to link the appointment code to a message
     *  * event indicates when the message should be sent out (during check in, as a reminder, etc)
     * @return string[][][][][]
     * @throws PDOException
     */
    /*
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
