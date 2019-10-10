<?php
#########################################
# Collection of global functions
#########################################

function say($inputString = "")
{
	echo $inputString ."\n";
}

function getPostContents()
{
	if(!empty($_POST))
	{
		return $_POST;
    }
    // elseif($_SERVER['CONTENT_TYPE'] === "application/x-www-form-urlencoded")
    // {

    // }
	elseif($_SERVER['CONTENT_TYPE'] === "application/json;charset=UTF-8")
	{
		return json_decode(file_get_contents('php://input'), true);
	}

	return [];
}

?>