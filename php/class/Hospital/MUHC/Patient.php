<?php declare(strict_types = 1);

namespace Orms\Hospital\MUHC;

use Orms\DateTime;

/** @psalm-immutable */
class Patient
{
    /**@var Mrn[] */

    #patient adt entries have the following format (some fields may be missing)
    /**
     * @param Mrn[] $mrns
     */
    function __construct(
        public DateTime $birthDt,
        public string   $birthPlace,
        public string   $fatherFirstName,
        public string   $fatherLastName,
        public string   $firstName,
        public string   $height,
        public string   $heightUnit,
        public string   $homeAddCity,
        public string   $homeAddPostalCode,
        public string   $homeAddProvince,
        public string   $homeAddress,
        public string   $homePhoneNumber,
        public string   $internalId,
        public string   $lastName,
        public string   $maritalStatus,
        public string   $motherFirstName,
        public array    $mrns,
        public string   $motherLastName,
        public string   $motherMaidenName,
        public string   $otherNameType,
        public string   $primaryLanguage,
        public DateTime $ramqExpDate,
        public string   $ramqNumber,
        public string   $sex,
        public string   $spouseFirstName,
        public string   $spouseLastName,
    ) {}

}

/** @psalm-immutable */
class Mrn
{
    function __construct(
        public string $active,
        public string $lastUpdate,
        public string $mrn,
        public string $mrnType,
    ) {}
}
