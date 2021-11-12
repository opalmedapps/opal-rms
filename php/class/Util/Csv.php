<?php

declare(strict_types=1);

namespace Orms\Util;

use Orms\Util\ArrayUtil;

class Csv
{
    /**
     * takes a just opened file handle for a csv file and inserts all the appointments within into the ORMS db
     *
     * @return list<mixed[]>
     */
    // $row = array_map('utf8_encode',$row); TODO: add encoding parameter to function
    public static function loadCsvFromFile(string $filePath, bool $headersPresent = true): array
    {
        $fileHandle = fopen($filePath, "r"); //$handle is stream

        if($fileHandle === false) return [];

        $data = [];
        $headers = ($headersPresent === true) ? fgetcsv($fileHandle) : [];
        $headers = $headers ?: [];

        while(($row = fgetcsv($fileHandle)) !== false)
        {
            //if a cell is empty, then it's value is null
            $row = array_map(fn($x) => ($x !== "") ? $x : null, $row);

            $row = ($headersPresent === true) ? array_combine($headers, $row) : $row;

            $data[] = $row ?: [];
        }

        return $data;
    }

    /**
     * writes an array to a csv file
     * the array must be either an array of assoc arrays or an array of arrays
     *
     * @param mixed[] $data
     */
    public static function writeCsvFromData(string $filePath, array $data): bool
    {
        $fileHandle = fopen($filePath, "w");

        if($fileHandle === false) return false;

        //check if the first row is assoc to get headers
        $firstRow = $data[0] ?? [];
        $includeHeaders = ArrayUtil::checkIfArrayIsAssoc($firstRow);

        if($includeHeaders === true)
        {
            $headers = array_keys($firstRow);
            fputcsv($fileHandle, $headers);
        }

        foreach($data as $x) {
            fputcsv($fileHandle, array_values($x));
        }

        return true;
    }
}
