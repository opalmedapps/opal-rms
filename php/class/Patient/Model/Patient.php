<?php declare(strict_types = 1);

namespace Orms\Patient\Model;

use Orms\DateTime;
use Orms\Patient\Model\Mrn;
use Orms\Patient\Model\Insurance;

/** @psalm-immutable */
class Patient
{
    /**
     * Do not use except in the PatientInterface class.
     */
    function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public Datetime $dateOfBirth,
        public ?string $phoneNumber,
        public int $opalStatus,
        public ?string $languagePreference,
        /** @var Mrn[] $mrns */ public array $mrns,
        /** @var Insurance[] $insurances */ public array $insurances
    ) {}

    /**
     *
     * @return Mrn[]
     */
    function getActiveMrns(): array
    {
        $mrns = array_values(array_filter($this->mrns,fn($x) => $x->active === TRUE));

        //sort the mrns to guarentee that they're always in the same order
        usort($mrns,function($a,$b) {
            return [$a->mrn,$a->site] <=> [$b->mrn,$b->site];
        });

        return $mrns;
    }

}
