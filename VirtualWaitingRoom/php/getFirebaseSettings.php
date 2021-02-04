<?php declare(strict_types = 1);
#script to get the parameters needed to connect to firebase

require_once __DIR__."/../../vendor/autoload.php";

use Orms\Config;

$configs = Config::getApplicationSettings()->system;

echo json_encode([
    "FirebaseUrl"       => $configs->firebaseUrl,
    "FirebaseSecret"    => $configs->firebaseSecret
]);

?>
