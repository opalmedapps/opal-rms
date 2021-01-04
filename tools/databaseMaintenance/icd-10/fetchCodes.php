<?php declare(strict_types = 1);

#fetches all ICD-10 diagnosis codes from the WHO'd online dictionary
require_once __DIR__ ."/../../../php/AutoLoader.php";

use GuzzleHttp\Client;

$data = getDataFromWho();

file_put_contents(__DIR__."/codes.json",json_encode($data));

function getDataFromWho(): array
{
    $url = "https://icd.who.int/browse10/2019/en/JsonGetRootConcepts?useHtml=false";
    $initialData = json_decode((new Client())->request("GET",$url)->getBody()->getContents(),TRUE);

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
    $data = json_decode((new Client())->request("GET","$url&ConceptId=$concept")->getBody()->getContents(),TRUE);

    return array_map(function($x) {
        return [
            "name" => $x["ID"],
            "desc" => $x["label"],
            "data" => ($x["isLeaf"] === FALSE) ? getConceptData($x["ID"]) : NULL
        ];
    },$data);
}

?>
