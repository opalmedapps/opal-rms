<?php

// Get the title of the report
$wsSQLTitle = "Select * from $dsCrossDatabse.QuestionnaireControl where QuestionnaireDBSerNum = $wsReportID;";

$qSQLTitle = $connection->query($wsSQLTitle);
$rowTitle = $qSQLTitle->fetch();

?>
