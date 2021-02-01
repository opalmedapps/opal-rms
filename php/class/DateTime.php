<?php declare(strict_types=1);

namespace Orms;

use JsonSerializable;

class DateTime extends \DateTime implements JsonSerializable
{
    function jsonSerialize()
    {
        return $this->format(static::ISO8601);
    }
}


?>
