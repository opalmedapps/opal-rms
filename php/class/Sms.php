<?php declare(strict_types = 1);

namespace Orms;

use DateTime;
use Exception;
use PDOException;
use GuzzleHttp\Exception\GuzzleException;

use Orms\Util\Encoding;
use Orms\Config;
use Orms\SmsConfig;
use Orms\Database;
use Orms\Sms\{SmsTwilio,SmsCdyne,SmsInterface,SmsReceivedMessage};
use Orms\Util\ArrayUtil;
use Orms\Logger;

Sms::__init();

class Sms
{
    private static ?SmsConfig $configs;
    private static SmsInterface $smsProvider;

    static function __init(): void
    {
        self::$configs = Config::getApplicationSettings()->sms;

        if(self::$configs?->provider === "twilio")     self::$smsProvider = new SmsTwilio();
        elseif(self::$configs?->provider === "cdyne")  self::$smsProvider = new SmsCdyne();
    }

    /**
     *
     * @throws GuzzleException
     * @throws PDOException
     */
    static function sendSms(string $clientNumber,string $message,string $serviceNumber = NULL): void
    {
        if(self::$configs === NULL || self::$configs->enabled !== TRUE) return;

        #by default, we send sms using Twilio
        if($serviceNumber === NULL) {
            $serviceNumber = self::$configs->longCodes[array_rand(self::$configs->longCodes)];
        }

        try
        {
            $messageId = self::$smsProvider->sendSms($clientNumber,$serviceNumber,$message);
            $result = "SUCCESS";
        }
        catch(Exception $e)
        {
            $messageId = "ORMS_sms_" . (new DateTime())->getTimestamp() . "_" . rand();
            $result = "FAILURE : {$e->getMessage()}";
        }

        Logger::LogSms(
            $clientNumber,
            $serviceNumber,
            $messageId,
            ucfirst(self::$configs->provider),
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
    static function getNewReceivedMessages(DateTime $timestamp): array
    {
        if(self::$configs === NULL || self::$configs->enabled !== TRUE) return [];

        $messages = self::$smsProvider->getReceivedMessages(self::$configs->longCodes,$timestamp);
        $messages = array_filter($messages,function($x) {
            return self::_checkIfMessageAlreadyReceived($x) === FALSE;
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
        if(self::$configs === NULL || self::$configs->enabled !== TRUE) return [];

        $dbh = Database::getOrmsConnection();
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

        return Encoding::utf8_encode_recursive($messages);
    }

    private static function _checkIfMessageAlreadyReceived(SmsReceivedMessage $message): bool
    {
        $dbh = Database::getLogsConnection();
        $query = $dbh->prepare("
            SELECT
                MessageId
            FROM
                SmsLog
            WHERE
                MessageId = :id

        ");
        $query->execute([":id" => $message->messageId]);

        return count($query->fetchAll()) > 0;
    }

}

?>
