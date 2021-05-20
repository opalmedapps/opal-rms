<?php declare(strict_types = 1);

namespace Orms\Sms;

use DateTime;
use Orms\Config;
use Orms\Sms\{SmsReceivedMessage,SmsInterface};
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class SmsTwilio implements SmsInterface
{
    private Client $client;

    function __construct()
    {
        $configs = Config::getApplicationSettings()->sms;

        $this->client = new Client($configs?->licenceKey,$configs?->token);
    }

    /**
     *
     * @throws TwilioException
     */
    function sendSms(string $clientNumber,string $serviceNumber,string $message): string
    {
        $sentSms = $this->client->messages->create($clientNumber,[
            "body" => $message,
            "from" => $serviceNumber
        ]);

        return $sentSms->sid;
    }

    /**
     *
     * @return SmsReceivedMessage[]
     */
    function getReceivedMessages(array $availableNumbers,DateTime $timestamp): array
    {
        $messages = [];

        foreach($availableNumbers as $number)
        {
            $incomingMessages = $this->client->messages->read([
                "to"        => $number,
                "dateSent"  => $timestamp
            ]);
            $messages = array_merge($messages,$incomingMessages);
        }

        #filter messages sent out by ORMS
        $messages = array_filter($messages,function($x) {
            return $x->direction === "inbound";
        });

         /**
         * @psalm-suppress UndefinedPropertyAssignment
         */
        $messages = array_map(function($x) {
            #remove plus sign from phone numbers
            #also remove international code client phone number because it might have been entered into the ORMS system without it
            $x->from = preg_replace("/^\+1/","",$x->from) ?? "";
            $x->to = preg_replace("/^\+/","",$x->to) ?? "";

            return new SmsReceivedMessage(
                $x->sid,
                $x->body,
                $x->from,
                $x->to,
                $x->dateSent->setTimezone((new DateTime)->getTimezone()),
                "Twilio"
            );
        },$messages);

        usort($messages,function(SmsReceivedMessage $a,SmsReceivedMessage $b) {
            return $a->timeReceived <=> $b->timeReceived;
        });

        return $messages;
    }

}
