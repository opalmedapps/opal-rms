<?php declare(strict_types = 1);

#convert ICD-10 data to a more convient format
require_once __DIR__ ."/../../../vendor/autoload.php";

$data = json_decode(file_get_contents(__DIR__."/data/raw_codes.json"),TRUE);

#do some cleaning up
$data = array_map("addElementForCodesWithoutSubcodes",$data);
$data = array_map("removeNestedCodeRanges",$data);
$data = array_map("stripNameFromNodes",$data);

#format the data to the desired format
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

file_put_contents(__DIR__."/data/processed_codes.json",json_encode([
    "chapters" => $listOfChapters,
    "codes" => $listOfCodes,
    "subcodes" => $listOfSubcodes
]));




##############################################
#adds a subcode for codes with no subcodes (the code itself is used as a subcode)
function addElementForCodesWithoutSubcodes(array $node)
{
    if($node["data"] === NULL && !preg_match("/\./",$node["name"])) {
        $node["data"] = [$node];
    }
    elseif($node["data"] !== NULL) {
        $node["data"] = array_map("addElementForCodesWithoutSubcodes",$node["data"]);
    }

    return $node;
}

#removes additional code ranges (like C00-C75) by attaching the leaf nodes to the first code range (group) node
function removeNestedCodeRanges(array $node,bool $getLeafNodes = FALSE)
{
    if($getLeafNodes === TRUE)
    {
        $nodes = new RecursiveIteratorIterator(new RecursiveArrayIterator($node["data"]),RecursiveIteratorIterator::SELF_FIRST);
        $leaves = [];
        foreach($nodes as $x) {
            if(is_array($x) && key_exists("name",$x) && !preg_match("/-/",$x["name"]) && $x["data"] !== NULL) $leaves[] = $x;
        }

        return $leaves;
    }
    elseif(preg_match("/-/",$node["name"]) && $getLeafNodes === FALSE)
    {
        $node["data"] = removeNestedCodeRanges($node,TRUE);
    }
    else
    {
        $node["data"] = array_map("removeNestedCodeRanges",$node["data"]);
    }

    return $node;
}

#removes the name of the concept from the description string
function stripNameFromNodes($node)
{
    $node["desc"] = preg_replace("/$node[name] /","",$node["desc"]);
    $node["data"] = ($node["data"] === NULL) ? NULL : array_map("stripNameFromNodes",$node["data"]);

    return $node;
}

?>
