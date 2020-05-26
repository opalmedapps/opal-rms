<?php

#Script to authenticate a user trying to log into ORMS
#creates a session on the server using memcache and returns the info needed for the front end to create a cookie that validates the user

#get the credentials the user entered
$postParams = json_decode(file_get_contents('php://input'), true);
#$postParams = $_POST;

$username = !empty($postParams["username"]) ? $postParams["username"] : NULL;
$password = !empty($postParams["password"]) ? $postParams["password"] : NULL;

#check if the credentials are valid in the AD
$url = 'https://fedauthfcp.rtss.qc.ca/fedauth/wsapi/login';
$fields = [
    'uid' => $username,
    'pwd' => $password,
    'institution' => '06-ciusss-cusm'
];

#url-ify the data for the POST
$fieldString = "";
foreach($fields as $key=>$value) {
    $fieldString .= $key.'='.$value.'&';
}
rtrim($fieldString, '&');

#make the request
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_POSTFIELDS,$fieldString);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
$requestResult = json_decode(curl_exec($ch),TRUE);
curl_close($ch);

#process the result of the AD call
#filter all groups that aren't ORMS
// $requestResult["roles"] = !empty($requestResult["roles"]) ? preg_grep("/GA-ORMS/",$requestResult["roles"]) : [];

#if the return status is 0, then the user's credentials are valid
#also check if the user is in an ORMS group
$validUser = array_key_exists("statusCode",$requestResult) && $requestResult["statusCode"] == 0 ? TRUE : FALSE;
// $validUser = $requestResult["roles"] !== [] ? $validUser : FALSE;

if($validUser === TRUE)
{
    #store the user session in the memcache
    $memcache = new Memcached; // connect to memcached on localhost port 11211
    $memcache->addServer('localhost',11211) or die("error");

    #generate cookie uniq session id
    $key = md5(uniqid(rand(), TRUE) .$_SERVER["REMOTE_ADDR"]. time());

    #$exists = $memcache->get($key);

    #create a cookie object
    $cookie = [
        "name" => "ormsAuth",
        "key" => $key,
        #"duration" => 30
    ];

    #contruct session value to be stored in memcached for the cookie session id.
    $value = "UserName=$username\r\n";
    $value .="Groups=". implode(":",$requestResult["roles"]) ."\r\n";
    $value .="RemoteIP=$_SERVER[REMOTE_ADDR]\r\n";
    #$value.="Expiration=$cookie[duration]\r\n"; //duration is handled server side; default is 1 hr and the time left refreshes on every page connection

    #store value for the key in memcache deamon
    $memcache->set($key,$value);

   # setcookie($cookie["name"],$cookie["key"],"/","",TRUE);

    echo json_encode(["Authentication success",$cookie]);
}
else
{
    echo json_encode(["Authentication failed",[]]);
}



?>
