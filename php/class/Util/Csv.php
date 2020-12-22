<?php declare(strict_types = 1);

namespace Orms\Util;

class Csv
{
    /**
     * takes a just opened file handle for a csv file and inserts all the appointments within into the ORMS db
     *
     * @param string $filePath
     * @param bool $headersPresent
     * @return array
     */
    // $row = array_map('utf8_encode',$row); TODO: add encoding parameter to function
    static function processCsvFile(string $filePath,bool $headersPresent = TRUE): array
    {
        $fileHandle = fopen($filePath,"r"); #$handle is stream

        if($fileHandle === FALSE) return [];

        $data = [];
        $headers = ($headersPresent === TRUE) ? fgetcsv($fileHandle) : [];

        while(($row = fgetcsv($fileHandle) ?? []) !== FALSE)
        {
            #if a cell is empty, then it's value is null
            $row = array_map(function($x) {
                return !empty($x) ? $x : NULL;
            },$row);

            $data[] = ($headersPresent === TRUE) ? array_combine($headers,$row) : $row;
        }

        return $data;
    }
}

?>
