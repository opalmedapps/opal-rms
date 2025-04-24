<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\System;

use DateTime;
use Orms\DataAccess\LoggerAccess;

class Logger
{
    /**
     *
     * @return list<array{
     *  messageId: string,
     *  timestamp: string
     * }>
     */
    public static function getLoggedSmsEvent(string $smsMessageId): array
    {
        return LoggerAccess::getLoggedSmsEvent($smsMessageId);
    }

    /**
     * Logs an sms event
     *
     */
    public static function logSmsEvent(
        string $clientNumber,
        string $serviceNumber,
        string $messageId,
        string $service,
        string $action,
        string $message,
        DateTime $timestamp,
        string $result
    ): void
    {
        LoggerAccess::insertSmsEvent(
            clientNumber:   $clientNumber,
            serviceNumber:  $serviceNumber,
            messageId:      $messageId,
            service:        $service,
            action:         $action,
            message:        $message,
            timestamp:      $timestamp,
            result:         $result
        );
    }

    public static function logVwrEvent(string $filename,string $identifier, string $type, string $message): void
    {
        LoggerAccess::logVwrEvent($filename,$identifier,$type,$message);
    }

    public static function getLastSmsProcessorRunTime(): ?DateTime
    {
        return LoggerAccess::getLastSmsProcessorRunTime();
    }

    public static function setLastSmsProcessorRunTime(DateTime $timestamp): void
    {
        LoggerAccess::setLastSmsProcessorRunTime($timestamp);
    }

    public static function logKioskEvent(?string $kioskInput, string $location, ?string $destination, ?string $centerImage, ?string $direction, ?string $message): void
    {
        LoggerAccess::logKioskEvent($kioskInput,$location,$destination,$centerImage,$direction,$message);
    }

    public static function logLoginEvent(string $userName, ?string $displayName, int $status, ?string $error): void
    {
        LoggerAccess::logLoginEvent($userName,$displayName,$status,$error);
    }
}
