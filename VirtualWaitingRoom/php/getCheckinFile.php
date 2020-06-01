<?php
//gets the location of the text files that contains the list of checked in patients and returns it to a webpage

include("loadConfigs.php");

$checkinFile = CHECKIN_FILE_URL;

echo json_encode($checkinFile);

?>
