<?php declare(strict_types = 1);

namespace Orms;

class ArrayUtil
{
    static function groupArrayByKey(array $arr,string $key,bool $keepKey = FALSE): array
    {
        $groupedArr = [];
        foreach($arr as $assoc)
        {
            $keyVal = $assoc[$key];
            if(!array_key_exists("$keyVal",$groupedArr)) $groupedArr["$keyVal"] = [];

            if($keepKey === FALSE) unset($assoc[$key]);
            $groupedArr["$keyVal"][] = $assoc;
        }

        ksort($groupedArr);
        return $groupedArr;
    }

    #recursive version of groupArrayByKey that repeats the grouping process for each input key
    static function groupArrayByKeyRecursive(array $arr,string ...$keys): array
    {
        $key = array_shift($keys);
        if($key === NULL) return $arr;

        $groupedArr = self::groupArrayByKey($arr,"$key");

        if($keys !== [])
        {
            foreach($groupedArr as &$subArr) {
                $subArr = self::groupArrayByKeyRecursive($subArr,...$keys);
            }
        }

        return $groupedArr;
    }

    #version of groupArrayByKeyRecursive that keeps the original keys intact
    static function groupArrayByKeyRecursiveKeepKeys(array $arr,string ...$keys): array
    {
        $key = array_shift($keys);
        if($key === NULL) return $arr;

        $groupedArr = self::groupArrayByKey($arr,"$key",TRUE);

        if($keys !== [])
        {
            foreach($groupedArr as &$subArr) {
                $subArr = self::groupArrayByKeyRecursiveKeepKeys($subArr,...$keys);
            }
        }

        return $groupedArr;
    }

    static function convertSingleElementArraysRecursive($arr)
    {
        if(gettype($arr) === "array")
        {
            foreach($arr as &$val) $val = self::convertSingleElementArraysRecursive($val);

            if(self::checkIfArrayIsAssoc($arr) === FALSE && count($arr) === 1) {
                $arr = $arr[0];
            }
        }

        return $arr;
    }

    static function checkIfArrayIsAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0,count($arr)-1);
    }

}

?>
