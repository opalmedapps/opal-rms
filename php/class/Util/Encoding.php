<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

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
            // Check if the string is already UTF-8 to avoid double encoding characters
            if (!mb_check_encoding($data, 'UTF-8')) {
                // Convert from ISO-8859-1 to UTF-8 if it’s not UTF-8
                return mb_convert_encoding($data, 'UTF-8', 'ISO-8859-1');
            }
            return $data; // Return as is if it's already UTF-8
        }
        elseif (is_array($data)) {
            return array_map(fn($x) => self::utf8_encode_recursive($x), $data);
        }

        return $data; // Return as is for non-string, non-array types
    }

    /**
     * Decodes the values of an array from UTF-8 to ISO-8859-1 (latin1).
     * Also works on array of arrays or other nested structures.
     *
     */
    public static function utf8_decode_recursive(mixed $data): mixed
    {
        if (is_string($data)) {
            // Check if the string is already ISO-8859-1
            if (!mb_check_encoding($data, 'ISO-8859-1')) {
                // Convert from UTF-8 to ISO-8859-1 if it’s not ISO-8859-1
                return mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8');
            }
            return $data; // Return as is if it's already ISO-8859-1
        }
        elseif (is_array($data)) {
            return array_map(fn($x) => self::utf8_decode_recursive($x), $data);
        }

        return $data;
    }
}
