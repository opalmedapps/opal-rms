<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Sms;

use DateTime;
use Exception;
use Orms\Config;
use Orms\DataAccess\SmsAccess;
use Orms\Sms\Internal\SmsCdyne;
use Orms\Sms\Internal\SmsClassInterface;
use Orms\Sms\Internal\SmsReceivedMessage;
use Orms\Sms\Internal\SmsTwilio;
use Orms\SmsConfig;
use Orms\System\Logger;
use Orms\Util\ArrayUtil;
use Orms\Util\Encoding;

SmsInterface::__init();

class SmsInterface
{
    private static ?SmsConfig $configs;
    private static ?SmsClassInterface $smsProvider;

    public static function __init(): void
    {
        self::$configs = Config::getApplicationSettings()->sms;

        self::$smsProvider = match(self::$configs?->provider) {
            "twilio" => new SmsTwilio(),
            "cdyne"  => new SmsCdyne(),
            default  => null
        };
    }

    public static function sendSms(string $clientNumber, string $message, string $serviceNumber = null): void
    {
        if(self::$configs === null || self::$smsProvider === null) {
            return;
        }

        //by default, we randomly pick one of the available numbers
        if($serviceNumber === null) {
            $serviceNumber = self::$configs->longCodes[array_rand(self::$configs->longCodes)];
        }

        try
        {
            $messageId = self::$smsProvider->sendSms($clientNumber, $serviceNumber, $message);
            $result = "SUCCESS";
        }
        catch(Exception $e)
        {
            $messageId = "ORMS_sms_" . (new DateTime())->getTimestamp() . "_" . rand();
            $result = "FAILURE : {$e->getMessage()}";
        }

        Logger::logSmsEvent(
            $clientNumber,
            $serviceNumber,
            $messageId,
            ucfirst(self::$configs->provider),
            "SENT",
            Encoding::utf8_decode_recursive($message),
            new DateTime(),
            $result
        );
    }

    /**
     *
     * @return SmsReceivedMessage[]
     */
    public static function getNewReceivedMessages(DateTime $timestamp): array
    {
        if(self::$configs === null || self::$smsProvider === null) {
            return [];
        }

        $messages = self::$smsProvider->getReceivedMessages(self::$configs->longCodes, $timestamp);
        $messages = array_filter($messages, fn($x) => self::_checkIfMessageAlreadyReceived($x) === false);

        foreach($messages as $x) {
            Logger::logSmsEvent(
                $x->clientNumber,
                $x->serviceNumber,
                $x->messageId,
                $x->provider,
                "RECEIVED",
                Encoding::utf8_decode_recursive($x->body),
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
     */
    public static function getPossibleSmsMessages(): array
    {
        if(self::$configs === null) {
            return [];
        }

        $messages = SmsAccess::getSmsAppointmentMessages();

        //convert all <nl> tags to newlines
        $messages = array_map(function($x) {
            $x["message"] = str_replace("<nl>","\n",$x["message"]);
            return $x;
        },$messages);

        $messages = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($messages, "specialityGroupId", "type", "event", "language");
        $messages = ArrayUtil::convertSingleElementArraysRecursive($messages);

        return Encoding::utf8_encode_recursive($messages);
    }

    /**
     *
     * param 'English'|'French' $language #too cumbersome to actually use with the code's current state
     */
    public static function getDefaultFailedCheckInMessage(string $language): ?string
    {
        if(self::$configs === null) {
            return null;
        }

        return match($language) {
            "English" => self::$configs->failedCheckInMessageEnglish,
            "French"  => self::$configs->failedCheckInMessageFrench,
            default   => null
        };
    }

    /**
     *
     * param 'English'|'French' $language //too cumbersome to actually use with the code's current state
     */
    public static function getDefaultUnknownCommandMessage(string $language): ?string
    {
        if(self::$configs === null) {
            return null;
        }

        return match($language) {
            "English" => self::$configs->unknownCommandMessageEnglish,
            "French"  => self::$configs->unknownCommandMessageFrench,
            default   => null
        };
    }

    private static function _checkIfMessageAlreadyReceived(SmsReceivedMessage $message): bool
    {
        return count(Logger::getLoggedSmsEvent($message->messageId)) > 0;
    }

}
