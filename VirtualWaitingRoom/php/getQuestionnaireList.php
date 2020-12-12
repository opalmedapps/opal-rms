<?php
//script to get the list of questionnaires a patient has answered

require("loadConfigs.php");

//get webpage parameters
$patientId = $_GET["patientId"];

$json = []; //output array

//connect to db
$dbWRM = new PDO(QUESTIONNAIRE_CONNECT,MYSQL_USERNAME,MYSQL_PASSWORD,$WRM_OPTIONS);

#####################################################################
//for now, set the patient ser num manually
//eventually, use the patient id to get the patient ser num in opal db and then get the questionnaires
####################################################################

$patientSer = '';
if($patientId == 9999996) {$patientSer = 9999996;}

//also get the prefered language from Opal
$lang = '';
if($patientId == 9999996) {$lang = 'EN';}

//==================================
//get questionnaire list
//==================================
$sqlQList = "CALL GetQuestionnairesList($patientSer,'$lang')";

//process results
$queryQList = $dbWRM->query($sqlQList);

while($row = $queryQList->fetch(PDO::FETCH_ASSOC))
{
    $json[] = $row;
}

//get individual questionnaire answers


//get historical questionnaire answers
//$sqlHistorical = "CALL GetPatientHistoricalQuestionnaires($patientSer,$questSer,$lang)";

//encode and return the json object
$json = utf8_encode_recursive($json);
echo json_encode($json,JSON_NUMERIC_CHECK);

?>
