<?php
/**************************************************
    Database configuration
**************************************************/
define( "DB_HOST", "" ); // Database location
define ( "DB_PORT", "");
define( "DB_NAME", "questionnaireDB2019" ); // Database Name

// PreProd Database for the patient information
define( "DB_X_NAME", "OpalDB" ); // Cross Database Name

define( "DB_USERNAME", "" ); // UserName
define( "DB_PASSWORD", "" ); // Password

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
