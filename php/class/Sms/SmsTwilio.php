<?php declare(strict_types = 1);

namespace Orms\Sms;

use DateTime;
use Orms\Config;
use Orms\Sms\SmsReceivedMessage;
use Twilio\Rest\Client;

SmsTwilio::__init();

class SmsTwilio
{
    private static Client $client;
    private static array $availableNumbers = [];

    static function __init(): void
    {
        $configs = Config::getConfigs("twilio");

        self::$client = new Client($configs["LICENCE_KEY"],$configs["TOKEN"]);
        self::$availableNumbers = $configs["REGISTERED_LONG_CODES"];
    }

    static function sendSms(string $clientNumber,string $message,string $serviceNumber = NULL): string
    {
        if($serviceNumber === NULL) {
            $serviceNumber = self::$availableNumbers[array_rand(self::$availableNumbers)];
        }

        $sentSms = self::$client->messages->create($clientNumber,[
            "body" => $message,
            "from" => $serviceNumber
        ]);

        return $sentSms->sid;
    }

    static function getReceivedMessages(DateTime $timestamp): array
    {

        $messages = [];

        foreach(self::$availableNumbers as $number)
        {
            $incomingMessages = self::$client->messages->read([
                "to"        => $number,
                "dateSent"  => $timestamp
            ]);
            $messages = array_merge($messages,$incomingMessages);
        }

        #filter messages sent out by ORMS
        $messages = array_filter($messages,function($x) {
            return $x->direction === "inbound";
        });

        $messages = array_map(function($x) {
            #remove plus sign from phone numbers
            #also remove international code client phone number because it might have been entered into the ORMS system without it
            $x->from = preg_replace("/^\+1/","",$x->from);
            $x->to = preg_replace("/^\+/","",$x->to);

            return new SmsReceivedMessage(
                $x->sid,
                $x->body,
                $x->from,
                $x->to,
                $x->dateSent->setTimezone((new DateTime)->getTimezone()),
                "Twilio"
            );
        },$messages);

        usort($messages,function($a,$b) {
            return $a->timeReceived <=> $b->timeReceived;
        });

        return $messages;
    }

}

?>
