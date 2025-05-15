<?php

declare(strict_types=1);

namespace Orms\Util;

class Encoding
{
    /**
     * Encodes the values of an array from ISO-8859-1 (latin1) to UTF-8.
     * Also works on array of arrays or other nested structures.
     *
     */
    public static function utf8_encode_recursive(mixed $data): mixed 
    {
        if (is_string($data)) {
            return mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
        } 
        elseif (is_array($data)) {
            return array_map(fn($x) => self::utf8_encode_recursive($x), $data);
        }

        return $data;
    }

    /**
     * Decodes the values of an array from UTF-8 to ISO-8859-1 (latin1).
     * Also works on array of arrays or other nested structures.
     *
     */
    public static function utf8_decode_recursive(mixed $data): mixed 
    {
        if (is_string($data)) {
            return mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8');
        } 
        elseif (is_array($data)) {
            return array_map(fn($x) => self::utf8_decode_recursive($x), $data);
        }
        
        return $data;
    }
}
