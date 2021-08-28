<?php

declare(strict_types=1);

namespace Orms\DataAccess;

use DateTime;
use Orms\DataAccess\Database;

class LoggerAccess
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
        $query = Database::getLogsConnection()->prepare("
            SELECT
                MessageId,
                SmsTimestamp
            FROM
                SmsLog
            WHERE
                MessageId = ?
        ");
        $query->execute([$smsMessageId]);

        return array_map(fn($x) => [
            "messageId" => $x["MessageId"],
            "timestamp" => $x["SmsTimestamp"]
        ],$query->fetchAll());
    }

    public static function insertSmsEvent(
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
        Database::getLogsConnection()->prepare("
            INSERT INTO SmsLog(
                SmsTimestamp,
                Result,
                Action,
                Service,
                MessageId,
                ServicePhoneNumber,
                ClientPhoneNumber,
                Message
            )
            VALUES(
                :timestamp,
                :result,
                :action,
                :service,
                :messageId,
                :serviceNum,
                :clientNum,
                :message
            )
        ")->execute([
            ":timestamp"   => $timestamp->format("Y-m-d H:i:s"),
            ":result"      => $result,
            ":action"      => $action,
            ":service"     => $service,
            ":messageId"   => $messageId,
            ":serviceNum"  => $serviceNumber,
            ":clientNum"   => $clientNumber,
            ":message"     => $message
        ]);
    }

    public static function logVwrEvent(string $filename,string $identifier, string $type, string $message): void
    {
        Database::getLogsConnection()->prepare("
            INSERT INTO VirtualWaitingRoomLog(
                DateTime,
                FileName,
                Identifier,
                Type,
                Message
            )
            VALUES(
                :date,
                :file,
                :id,
                :type,
                :message
            )
        ")->execute([
            ":date"     => (new DateTime())->format("Y-m-d H:i:s"),
            ":file"     => $filename,
            ":id"       => $identifier,
            ":type"     => $type,
            ":message"  => $message,
        ]);
    }
}
