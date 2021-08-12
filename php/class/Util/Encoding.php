<?php

declare(strict_types=1);

namespace Orms\Util;

class Encoding
{
    /**
     * Encodes the values of an array from utf8 to latin1.
     * Also works on array of arrays or other nested structures.
     *
     */
    public static function utf8_encode_recursive(mixed $data): mixed
    {
        if(is_string($data)) {
            return utf8_encode($data);
        }
        elseif(is_array($data)) {
            return array_map(fn($x) => self::utf8_encode_recursive($x), $data);
        }

        return $data;
    }

    /**
     * Decodes the values of an array from utf8 to latin1.
     * Also works on array of arrays or other nested structures.
     *
     */
    public static function utf8_decode_recursive(mixed $data): mixed
    {
        if(is_string($data)) {
            return utf8_decode($data);
        }
        elseif(is_array($data)) {
            return array_map(fn($x) => self::utf8_decode_recursive($x), $data);
        }

        return $data;
    }
}
