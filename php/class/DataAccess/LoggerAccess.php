<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

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

    public static function getLastSmsProcessorRunTime(): ?DateTime
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                LastReceivedSmsFetch
            FROM
                Cron
            WHERE
                System = 'ORMS'
        ");
        $query->execute();

        $lastRun = $query->fetchAll()[0]["LastReceivedSmsFetch"] ?? null;

        if($lastRun === null) {
            return null;
        }

        return new DateTime($lastRun);
    }

    public static function setLastSmsProcessorRunTime(DateTime $timestamp): void
    {
        Database::getOrmsConnection()->prepare("
            INSERT INTO Cron(
                System,
                LastReceivedSmsFetch
            )
            VALUES(
                'ORMS',
                ?
            )
            ON DUPLICATE KEY UPDATE
                System               = VALUES(System),
                LastReceivedSmsFetch = VALUES(LastReceivedSmsFetch)
        ")->execute([$timestamp->format("Y-m-d H:i:s")]);
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

    public static function logKioskEvent(?string $kioskInput, string $location, ?string $destination, ?string $centerImage, ?string $direction, ?string $message): void
    {
        Database::getLogsConnection()->prepare("
            INSERT INTO KioskLog(
                KioskInput,
                KioskLocation,
                PatientDestination,
                CenterImage,
                ArrowDirection,
                DisplayMessage
            )
            VALUES(
                :input,
                :location,
                :destination,
                :centerImage,
                :direction,
                :message
            )
        ")->execute([
            ":input"        => $kioskInput,
            ":location"     => $location,
            ":destination"  => $destination,
            ":centerImage"  => $centerImage,
            ":direction"    => $direction,
            ":message"      => $message,
        ]);
    }

    public static function logLoginEvent(string $userName, ?string $displayName, int $status, ?string $error)
    {
        Database::getLogsConnection()->prepare("
            INSERT INTO LoginLog(
                UserName,
                DisplayName,
                Status,
                Error,
                LoginIPAddress
            )
            VALUES(
                :userName,
                :displayName,
                :status,
                :error,
                :loginIP
            )
        ")->execute([
            ":userName"    => $userName,
            ":displayName" => $displayName,
            ":status"      => $status,
            ":error"       => $error,
            ":loginIP"     => empty($_SERVER["REMOTE_ADDR"]) ? gethostname() : $_SERVER["REMOTE_ADDR"]
        ]);
    }
}
