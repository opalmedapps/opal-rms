<?php declare(strict_types = 1);
#script to get the parameters needed to connect to firebase

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Config;

$configs = Config::getConfigs("vwr");

echo json_encode([
    "FirebaseUrl"       => $configs["FIREBASE_URL"],
    "FirebaseSecret"    => $configs["FIREBASE_SECRET"]
]);

?>
