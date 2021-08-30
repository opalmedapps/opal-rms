<?php

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

}
