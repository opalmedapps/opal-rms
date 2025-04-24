<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms;

use JsonSerializable;

class DateTime extends \DateTime implements JsonSerializable
{
    public static function createFromFormatN(string $format, string $datetimeString, ?\DateTimeZone $timezone = null): ?self
    {
        return DateTime::createFromFormat($format, $datetimeString, $timezone) ?: null;
    }

    public function modifyN(string $modify): ?self
    {
        return $this->modify($modify) ?: null; /** @phpstan-ignore-line */ //for some reason, phpstan thinks that modify always returns a datetime
    }

    public function jsonSerialize(): string
    {
        return $this->format(\DateTime::ATOM);
    }

}
