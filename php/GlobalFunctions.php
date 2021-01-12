<?php declare(strict_types=1);
#########################################
# Collection of global functions
#########################################

error_reporting(E_ALL);
ini_set("error_log", __DIR__."/../php-error.log");
date_default_timezone_set("America/Montreal");

/**
 *
 * @return mixed[]
 */
function getPostContents(): array
{
    if(!empty($_POST))
    {
        return $_POST;
    }
    // elseif($_SERVER['CONTENT_TYPE'] === "application/x-www-form-urlencoded")
    // {

    // }
    elseif($_SERVER['CONTENT_TYPE'] === "application/json;charset=UTF-8")
    {
        return json_decode(file_get_contents('php://input') ?: "",TRUE) ?? [];
    }

    return [];
}

/**
 *
 * @param mixed $data
 * @return mixed
 */
function utf8_encode_recursive($data)
{
    if (is_array($data)) foreach ($data as $key => $val) $data[$key] = utf8_encode_recursive($val);
    elseif (is_string ($data)) return utf8_encode($data);

    return $data;
}

/**
 *  encodes the values of an array from utf8 to latin1
 *  also works on array of arrays or other nested structures
 * @param mixed $data
 * @return mixed
 */
function utf8_decode_recursive($data)
{
    if (is_array($data)) foreach ($data as $key => $val) $data[$key] = utf8_decode_recursive($val);
    elseif (is_string ($data)) return utf8_decode($data);

    return $data;
}

?>
