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
	elseif ($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		return json_decode(file_get_contents('php://input'), true);
	}

	return [];
}

?>