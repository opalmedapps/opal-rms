<?php
###############################################
# Loads the appropriate config file depending on the git branch of the repository
###############################################

//get current git branch
exec("git symbolic-ref -q HEAD", $output);

$gitBranch = $output[0];
$gitBranch = explode("/",$gitBranch);
$gitBranch = end($gitBranch); //filter everything but the name

//if on preprod or master branch, load configs for live use
//otherwise load the default dev configs

if($gitBranch == 'testing' or $gitBranch == 'master' or $gitBranch == 'aria15')
{
	require("configFileLive.php");
}
else
{
	require("configFileDev.php");
}



?>
