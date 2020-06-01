<?php
//set off all error for security purposes
error_reporting(E_ALL);

//define some contstants

//correct contents to read dev database
define( "DB_HOST", "" );
define( "DB_PORT", "");
define( "DB_USERNAME", "opalAdmin" );
define( "DB_PASSWORD", "" );
define( "DB_NAME", "questionnaireDB2019");

define( "OPAL_DB", "OpalDB");

function utf8_encode_recursive($data)
{
    if (is_array($data)) foreach ($data as $key => $val) $data[$key] = utf8_encode_recursive($val);
    elseif (is_string ($data)) return utf8_encode($data);

    return $data;
}

#encodes the values of an array from utf8 to latin1
#also works on array of arrays or other nested structures
function utf8_decode_recursive($data)
{
    if (is_array($data)) foreach ($data as $key => $val) $data[$key] = utf8_decode_recursive($val);
    elseif (is_string ($data)) return utf8_decode($data);

    return $data;
}

?>
