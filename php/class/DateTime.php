<?php declare(strict_types=1);

namespace Orms;

use DateTimeZone;
use JsonSerializable;

class DateTime extends \DateTime implements JsonSerializable
{
    static function createFromUnixTimestamp(int $timestamp): self
    {
        return (new DateTime("@$timestamp"))->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    static function createFromFormatN(string $format,string $datetimeString,?\DateTimeZone $timezone = null): ?self
    {
        return DateTime::createFromFormat($format,$datetimeString,$timezone) ?: NULL;
    }

    function modifyN(string $modify): ?self
    {
        return $this->modify($modify) ?: NULL; /** @phpstan-ignore-line */ //for some reason, phpstan thinks that modify always returns a datetime
    }

    function jsonSerialize()
    {
        return $this->format(static::ISO8601);
    }

}

?>
