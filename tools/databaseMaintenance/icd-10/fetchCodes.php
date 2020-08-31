<?php declare(strict_types = 1);

#fetches all ICD-10 diagnosis codes from the WHO'd online dictionary
require_once __DIR__ ."/../../../php/AutoLoader.php";

$data = getDataFromWho();

file_put_contents(__DIR__."/codes.json",json_encode($data));

function getDataFromWho(): array
{
    $url = "https://icd.who.int/browse10/2019/en/JsonGetRootConcepts?useHtml=false";
    $initialData = json_decode(file_get_contents($url),TRUE);
    // $initialData = [$initialData[0]];

    return array_map(function($x) {
        return [
            "name" => $x["ID"],
            "desc" => $x["label"],
            "data" => getConceptData($x["ID"])
        ];
    },$initialData);
}

function getConceptData(string $concept): array
{
    $url = "https://icd.who.int/browse10/2019/en/JsonGetChildrenConcepts?useHtml=false&showAdoptedChildren=true";
    $data = json_decode(file_get_contents("$url&ConceptId=$concept"),TRUE);
    // $data = [$data[0]];

    return array_map(function($x) {
        return [
            "name" => $x["ID"],
            "desc" => $x["label"],
            "data" => ($x["isLeaf"] === FALSE) ? getConceptData($x["ID"]) : NULL
        ];
    },$data);
}

?>
