<?php declare(strict_types = 1);

namespace Orms\Sms;

use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Orms\Config;
use Orms\ArrayUtil;
use Orms\Sms\SmsReceivedMessage;
use RuntimeException;

SmsCdyne::__init();

class SmsCdyne
{
    private static string $sendMessageUrl = "https://messaging.cdyne.com/Messaging.svc/SendMessage";
    private static string $getUnreadMessagesUrl = "https://messaging.cdyne.com/Messaging.svc/ReadIncomingMessages";
    private static string $licence;
    private static Client $client;
    /**
     * @var string[]
     */
    private static array $availableNumbers = [];

    /**
     *
     * @return void
     */
    static function __init(): void
    {
        $configs = Config::getConfigs("cdyne");

        self::$client = new Client();
        self::$licence = $configs["LICENCE_KEY"];
        self::$availableNumbers = $configs["REGISTERED_LONG_CODES"];
    }

    /**
     *
     * @param string $clientNumber
     * @param string $message
     * @param string|null $serviceNumber
     * @return null|string
     * @throws GuzzleException
     * @throws RuntimeException
     */
    static function sendSms(string $clientNumber,string $message,string $serviceNumber = NULL): ?string
    {
        if($serviceNumber === NULL) {
            $serviceNumber = self::$availableNumbers[array_rand(self::$availableNumbers)];
        }

        $sentSms = self::$client->request("POST",self::$sendMessageUrl,[
            "json" => [
                "Body"          => $message,
                "LicenseKey"    => self::$licence,
                "From"          => $serviceNumber,
                "To"            => [$clientNumber],
                "Concatenate"   => TRUE,
                "UseMMS"        => FALSE,
                "IsUnicode"     => TRUE
            ]
        ])->getBody()->getContents();
        $sentSms = json_decode($sentSms,TRUE)[0];

        return $sentSms["MessageID"] ?? NULL;
    }

    /**
     *
     * @return SmsReceivedMessage[]
     * @throws GuzzleException
     */
    static function getReceivedMessages(): array
    {
        try
        {
            $messages = self::$client->request("POST",self::$getUnreadMessagesUrl,[
                "json" => [
                    "LicenseKey" => self::$licence,
                    "UnreadMessagesOnly" => TRUE
                ]
            ])->getBody()->getContents();
            $messages = json_decode($messages,TRUE) ?? [];

            #messages have the following structure:
            #the values are either string or NULL
            /*
            Array
                (
                    [Attachments]
                    [From]
                    [IncomingMessageID]
                    [OutgoingMessageID]
                    [Payload]
                    [ReceivedDate] => "/Date(1590840267010-0700)/" //.NET DataContractJsonSerializer format
                    [Subject]
                    [To]
                    [Udh]
                )
            */

            #long messages are received in chunks so piece together the full message
            $messages = ArrayUtil::groupArrayByKey($messages,"OutgoingMessageID");
            $messages = array_map(function($x) {
                $msg = array_reduce($x,function($acc,$y) {
                    return $acc . $y["Payload"];
                },"");

                $x = array_merge(...$x);
                $x["Payload"] = $msg;

                #also convert the received utc timestamp into the local one
                #timezone isn't really utc; it actually has an offset
                $timestampWithOffset = preg_replace("/[^0-9 -]/","",$x["ReceivedDate"]);
                $timestamp = (int) (substr($timestampWithOffset,0,-5)/1000);
                $tzOffset = (new DateTime("",new DateTimeZone(substr($timestampWithOffset,-5))))->getOffset();
                $utcTime = (new DateTime("@$timestamp"))->modify("$tzOffset second");

                $x["ReceivedDate"] = $utcTime->setTimezone(new DateTimeZone(date_default_timezone_get()));

                return $x;
            },$messages);
        }
        catch(Exception $e)
        {
            $messages = [];
        }

        $messages = array_map(function($x) {
            #remove international code client phone number because it might have been entered into the ORMS system without it
            if(strlen($x["From"]) === 11) $x["From"] = substr($x["From"],1);

            return new SmsReceivedMessage(
                $x["IncomingMessageID"],
                $x["Payload"],
                $x["From"],
                $x["To"],
                $x["ReceivedDate"],
                "Cdyne"
            );
        },$messages);

        usort($messages,function($a,$b) {
            return $a->timeReceived <=> $b->timeReceived;
        });

        return $messages;
    }

}

?>
