<?php declare(strict_types = 1);
#script to get the parameters needed to connect to firebase

require("loadConfigs.php");

echo json_encode([
    "FirebaseUrl"       => FIREBASE_URL,
    "FirebaseSecret"    => FIREBASE_SECRET
]);

?>
