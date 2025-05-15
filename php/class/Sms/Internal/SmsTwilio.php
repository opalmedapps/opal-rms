<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Sms\Internal;

use DateTime;
use Orms\Config;
use Orms\Sms\Internal\SmsClassInterface;
use Orms\Sms\Internal\SmsReceivedMessage;
use Twilio\Exceptions\EnvironmentException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class SmsTwilio implements SmsClassInterface
{
    private Client $client;

    public function __construct()
    {
        $configs = Config::getApplicationSettings()->sms;

        $this->client = new Client($configs?->licenceKey, $configs?->token);
    }

    /**
     *
     * @throws TwilioException
     */
    public function sendSms(string $clientNumber, string $serviceNumber, string $message): string
    {
        $sentSms = $this->client->messages->create($clientNumber, [
            "body" => $message,
            "from" => $serviceNumber
        ]);

        return $sentSms->sid;
    }

    /**
     *
     * @return SmsReceivedMessage[]
     */
    public function getReceivedMessages(array $availableNumbers, DateTime $timestamp): array
    {
        $messages = [];

        foreach($availableNumbers as $number)
        {
            try {
                $incomingMessages = $this->client->messages->read([
                    "to"        => $number,
                    "dateSent"  => $timestamp
                ]);
            }
            /*
            Sometimes calling the twilio api fails (unknown why), throwing an EnvironmentException:
                * EnvironmentException: TCP connection reset by peer
                * EnvironmentException: Operation timed out after 60001 milliseconds with 0 out of 0 bytes
                * EnvironmentException: Could not resolve host: api.twilio.com: Unknown error
            In any case, returing an empty array and letting the code continue is fine because the cron (the only thing that calls this function), will simply run again in a few seconds
            */
            catch(EnvironmentException) {
                $incomingMessages = [];
            }

            $messages = array_merge($messages, $incomingMessages);
        }

        //filter messages sent out by ORMS
        $messages = array_filter($messages, fn($x) => $x->direction === "inbound");

         /**
         * @psalm-suppress UndefinedPropertyAssignment
         */
        $messages = array_map(function($x) {
            //remove plus sign from phone numbers
            //also remove international code client phone number because it might have been entered into the ORMS system without it
            $x->from = preg_replace("/^\+1/", "", $x->from) ?? "";
            $x->to = preg_replace("/^\+/", "", $x->to) ?? "";

            return new SmsReceivedMessage(
                $x->sid,
                $x->body,
                $x->from,
                $x->to,
                $x->dateSent->setTimezone((new DateTime())->getTimezone()),
                "Twilio"
            );
        }, $messages);

        usort($messages, fn(SmsReceivedMessage $a, SmsReceivedMessage $b) => $a->timeReceived <=> $b->timeReceived);

        return $messages;
    }

}
