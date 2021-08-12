<?php

declare(strict_types=1);

namespace Orms;

use DateTimeZone;
use JsonSerializable;

class DateTime extends \DateTime implements JsonSerializable
{
    public static function createFromUnixTimestamp(int $timestamp): self
    {
        return (new DateTime("@$timestamp"))->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    public static function createFromFormatN(string $format, string $datetimeString, ?\DateTimeZone $timezone = null): ?self
    {
        return DateTime::createFromFormat($format, $datetimeString, $timezone) ?: null;
    }

    public function modifyN(string $modify): ?self
    {
        return $this->modify($modify) ?: null; /** @phpstan-ignore-line */ //for some reason, phpstan thinks that modify always returns a datetime
    }

    public function jsonSerialize()
    {
        return $this->format(static::ISO8601);
    }

}
