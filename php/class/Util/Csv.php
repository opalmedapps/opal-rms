<?php declare(strict_types = 1);

namespace Orms\Util;

use Orms\ArrayUtil;

class Csv
{
    /**
     * takes a just opened file handle for a csv file and inserts all the appointments within into the ORMS db
     *
     * @return array[]
     */
    // $row = array_map('utf8_encode',$row); TODO: add encoding parameter to function
    static function loadCsvFromFile(string $filePath,bool $headersPresent = TRUE): array
    {
        $fileHandle = fopen($filePath,"r"); #$handle is stream

        if($fileHandle === FALSE) return [];

        $data = [];
        $headers = ($headersPresent === TRUE) ? fgetcsv($fileHandle) : [];
        $headers = $headers ?: [];

        while(($row = fgetcsv($fileHandle) ?? []) !== FALSE)
        {
            #if a cell is empty, then it's value is null
            $row = array_map(function($x) {
                return ($x !== "") ? $x : NULL;
            },$row);

            $row = ($headersPresent === TRUE) ? array_combine($headers,$row) : $row;

            $data[] = $row ?: [];
        }

        return $data;
    }

    /**
     * writes an array to a csv file
     * the array must be either an array of assoc arrays or an array of arrays
     *
     * @param array[] $data
     */
    static function writeCsvFromData(string $filePath,array $data): bool
    {
        $fileHandle = fopen($filePath,"w");

        if($fileHandle === FALSE) return FALSE;

        #check if the first row is assoc to get headers
        $firstRow = $data[0] ?? [];
        $includeHeaders = ArrayUtil::checkIfArrayIsAssoc($firstRow);

        if($includeHeaders === TRUE)
        {
            $headers = array_keys($firstRow);
            fputcsv($fileHandle,$headers);
        }

        foreach($data as $x) {
            fputcsv($fileHandle,array_values($x));
        }

        return TRUE;
    }
}

?>
