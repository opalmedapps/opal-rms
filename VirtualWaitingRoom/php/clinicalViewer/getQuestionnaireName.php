<?php declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Opal;

echo json_encode(utf8_encode_recursive(Opal::getListOfQuestionnaires()));

?>
