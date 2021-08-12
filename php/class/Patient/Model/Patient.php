<?php

declare(strict_types=1);

namespace Orms\Patient\Model;

use Orms\DateTime;
use Orms\Patient\Model\Insurance;
use Orms\Patient\Model\Mrn;

/** @psalm-immutable */
class Patient
{
    /**
     * Only use in PatientInferface class and DataAccess layer.
     */
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public Datetime $dateOfBirth,
        public string $sex,
        public ?string $phoneNumber,
        public int $opalStatus,
        public ?string $languagePreference,
        /** @var Mrn[] $mrns */
        public array $mrns,
        /** @var Insurance[] $insurances */
        public array $insurances
    ) {}

    /**
     *
     * @return Mrn[]
     */
    public function getActiveMrns(): array
    {
        $mrns = array_values(array_filter($this->mrns, fn($x) => $x->active === true));

        //sort the mrns to guarentee that they're always in the same order
        usort($mrns, fn($a, $b) => [$a->mrn,$a->site] <=> [$b->mrn,$b->site]);

        return $mrns;
    }

}
