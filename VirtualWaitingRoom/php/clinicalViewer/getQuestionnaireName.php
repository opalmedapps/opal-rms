<?php declare(strict_types=1);

require_once __DIR__."/../../../vendor/autoload.php";

use Orms\Util\Encoding;
use Orms\Opal;

echo json_encode(Encoding::utf8_encode_recursive(Opal::getListOfQuestionnaires()));

?>
