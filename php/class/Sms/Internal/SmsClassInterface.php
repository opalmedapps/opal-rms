<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Sms\Internal;

use DateTime;

interface SmsClassInterface
{
    public function sendSms(string $clientNumber, string $serviceNumber, string $message): string;

    /**
     * @param string[] $availableNumbers
     * @return SmsReceivedMessage[]
     */
    public function getReceivedMessages(array $availableNumbers, DateTime $timestamp): array;
}
