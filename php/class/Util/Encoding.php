<?php declare(strict_types = 1);

namespace Orms\Util;

class Encoding
{
    /**
     *
     * @param mixed $data
     * @return mixed
     */
    static function utf8_encode_recursive($data)
    {
        if (is_array($data)) foreach ($data as $key => $val) $data[$key] = self::utf8_encode_recursive($val);
        elseif (is_string ($data)) return utf8_encode($data);

        return $data;
    }

    /**
     * encodes the values of an array from utf8 to latin1
     * also works on array of arrays or other nested structures
     * @param mixed $data
     * @return mixed
     */
    static function utf8_decode_recursive($data)
    {
        if (is_array($data)) foreach ($data as $key => $val) $data[$key] = self::utf8_decode_recursive($val);
        elseif (is_string ($data)) return utf8_decode($data);

        return $data;
    }
}
