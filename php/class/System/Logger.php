<?php

declare(strict_types=1);

namespace Orms\System;

use DateTime;
use Orms\DataAccess\LoggerAccess;

class Logger
{
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
}
