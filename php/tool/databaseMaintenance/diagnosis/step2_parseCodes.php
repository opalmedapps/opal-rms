<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

//convert ICD-10 data to a more convient format
require_once __DIR__ ."/../../../../vendor/autoload.php";

$data = json_decode(file_get_contents(__DIR__."/data/raw_codes.json") ?: "", true);

//do some cleaning up
$data = array_map("addElementForCodesWithoutSubcodes", $data);
$data = array_map("removeNestedCodeRanges", $data);
$data = array_map("stripNameFromNodes", $data);

//format the data to the desired format
$listOfChapters = [];
$listOfCodes = [];
$listOfSubcodes = [];
foreach($data as $chapter)
{
    $listOfChapters[] = [
        "chapter" => $chapter["name"],
        "description" => $chapter["desc"]
    ];

    foreach($chapter["data"] as $group)
    {
        foreach($group["data"] as $code)
        {
            $listOfCodes[] = [
                "chapter" => $chapter["name"],
                "category" => $group["desc"],
                "code" => $code["name"],
                "description" => $code["desc"]
            ];

            foreach($code["data"] as $subcode)
            {
                $listOfSubcodes[] = [
                    "chapter" => $chapter["name"],
                    "category" => $group["desc"],
                    "code" => $code["name"],
                    "subcode" => $subcode["name"],
                    "description" => $subcode["desc"]
                ];
            }
        }
    }
}

file_put_contents(__DIR__."/data/processed_codes.json", json_encode([
    "chapters" => $listOfChapters,
    "codes" => $listOfCodes,
    "subcodes" => $listOfSubcodes
]));




//#############################################
/**
 * adds a subcode for codes with no subcodes (the code itself is used as a subcode)
 * @param mixed[] $node
 * @return mixed[]
 */
function addElementForCodesWithoutSubcodes(array $node): array
{
    if($node["data"] === null && !preg_match("/\./", $node["name"])) {
        $node["data"] = [$node];
    }
    elseif($node["data"] !== null) {
        $node["data"] = array_map("addElementForCodesWithoutSubcodes", $node["data"]);
    }

    return $node;
}

/**
 * removes additional code ranges (like C00-C75) by attaching the leaf nodes to the first code range (group) node
 * @param mixed[] $node
 * @return mixed[]
 */
function removeNestedCodeRanges(array $node, bool $getLeafNodes = false): array
{
    if($getLeafNodes === true)
    {
        $nodes = new RecursiveIteratorIterator(new RecursiveArrayIterator($node["data"]), RecursiveIteratorIterator::SELF_FIRST);
        $leaves = [];
        foreach($nodes as $x) {
            if(is_array($x) && key_exists("name", $x) && !preg_match("/-/", $x["name"]) && $x["data"] !== null) $leaves[] = $x;
        }

        return $leaves;
    }
    elseif(preg_match("/-/", $node["name"]))
    {
        $node["data"] = removeNestedCodeRanges($node, true);
    }
    else
    {
        $node["data"] = array_map("removeNestedCodeRanges", $node["data"]);
    }

    return $node;
}

/**
 * removes the name of the concept from the description string
 * @param mixed[] $node
 * @return mixed[]
 */
function stripNameFromNodes(array $node): array
{
    $node["desc"] = preg_replace("/$node[name] /", "", $node["desc"]);
    $node["data"] = ($node["data"] === null) ? null : array_map("stripNameFromNodes", $node["data"]);

    return $node;
}
