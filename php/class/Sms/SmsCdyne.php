<?php declare(strict_types = 1);

namespace Orms\Sms;

use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

use Orms\Config;
use Orms\ArrayUtil;
use Orms\Sms\{SmsInterface,SmsReceivedMessage};

class SmsCdyne implements SmsInterface
{
    private string $sendMessageUrl = "https://messaging.cdyne.com/Messaging.svc/SendMessage";
    private string $getUnreadMessagesUrl = "https://messaging.cdyne.com/Messaging.svc/ReadIncomingMessages";
    private string $licence;
    private Client $client;

    function __construct()
    {
        $configs = Config::getApplicationSettings()->sms;

        $this->client = new Client();

        //$configs?->licenceKey ?? "" doesn't work for now with psalm due to a bug
        $licenceKey = $configs?->licenceKey;
        $licenceKey ??= "";
        $this->licence = $licenceKey;
    }

    /**
     *
     * @throws GuzzleException
     * @throws RuntimeException
     */
    function sendSms(string $clientNumber,string $serviceNumber,string $message): string
    {
        $sentSms = $this->client->request("POST",$this->sendMessageUrl,[
            "json" => [
                "Body"          => $message,
                "LicenseKey"    => $this->licence,
                "From"          => $serviceNumber,
                "To"            => [$clientNumber],
                "Concatenate"   => TRUE,
                "UseMMS"        => FALSE,
                "IsUnicode"     => TRUE
            ]
        ])->getBody()->getContents();
        $sentSms = json_decode($sentSms,TRUE)[0];

        return $sentSms["MessageID"];
    }

    /**
     *
     * @return SmsReceivedMessage[]
     * @throws GuzzleException
     */
    function getReceivedMessages(array $availableNumbers,DateTime $timestamp): array
    {
        try
        {
            $messages = $this->client->request("POST",$this->getUnreadMessagesUrl,[
                "json" => [
                    "LicenseKey" => $this->licence,
                    "UnreadMessagesOnly" => TRUE
                ]
            ])->getBody()->getContents();
            $messages = json_decode($messages,TRUE) ?? [];

            //messages have the following structure:
            //the values are either string or NULL
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
                $msg = array_reduce($x,function(string $acc,array $y) {
                    return $acc . $y["Payload"];
                },"");

                $x = array_merge(...$x);
                $x["Payload"] = $msg;

                #also convert the received utc timestamp into the local one
                #timezone isn't really utc; it actually has an offset
                $timestampWithOffset = preg_replace("/[^0-9 -]/","",$x["ReceivedDate"]);
                $timestamp = (int) substr($timestampWithOffset,0,-5) / 1000;
                $tzOffset = (new DateTime("",new DateTimeZone(substr($timestampWithOffset,-5))))->getOffset();
                $utcTime = (new DateTime("@$timestamp"))->modify("$tzOffset second");

                $x["ReceivedDate"] = $utcTime->setTimezone(new DateTimeZone(date_default_timezone_get()));

                return $x;
            },$messages);
        }
        catch(Exception)
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

        usort($messages,function(SmsReceivedMessage $a,SmsReceivedMessage $b) {
            return $a->timeReceived <=> $b->timeReceived;
        });

        return $messages;
    }

}

?>
