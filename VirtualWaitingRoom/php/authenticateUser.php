<?php declare(strict_types = 1);

#Script to authenticate a user trying to log into ORMS

#get the credentials the user entered
$postParams = getPostContents();

$username = !empty($postParams["username"]) ? $postParams["username"] : NULL;
$password = !empty($postParams["password"]) ? $postParams["password"] : NULL;

//the intmed user is diabled for this script...
if(strtolower($username ?? "") === "intmed")
{
    echo json_encode(["valid" => FALSE]);
    exit;
}

#check if the credentials are valid in the AD
$url = 'https://fedauthfcp.rtss.qc.ca/fedauth/wsapi/login';
$fields = [
    'uid' => $username,
    'pwd' => $password,
    'institution' => '06-ciusss-cusm'
];

#make the request
$curlConn = curl_init($url);
curl_setopt_array($curlConn,[
    CURLOPT_POSTFIELDS => http_build_query($fields),
    CURLOPT_RETURNTRANSFER => TRUE
]);
$requestResult = json_decode(curl_exec($curlConn),TRUE);
curl_close($curlConn);

#process the result of the AD call
#filter all groups that aren't ORMS
#$requestResult["roles"] = !empty($requestResult["roles"]) ? preg_grep("/GA-ORMS/",$requestResult["roles"]) : [];

#if the return status is 0, then the user's credentials are valid
#also check if the user is in an ORMS group
$validUser = array_key_exists("statusCode",$requestResult) && $requestResult["statusCode"] == 0 ? TRUE : FALSE;
#$validUser = $requestResult["roles"] !== [] ? $validUser : FALSE;

if($validUser === TRUE) echo json_encode(["valid" => TRUE]);
else echo json_encode(["valid" => FALSE]);

?>
