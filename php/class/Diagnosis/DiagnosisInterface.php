<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Diagnosis;

use Orms\DataAccess\DiagnosisAccess;
use Orms\DateTime;
use Orms\Diagnosis\Model\Diagnosis;
use Orms\Diagnosis\Model\PatientDiagnosis;

class DiagnosisInterface
{
    public static function insertPatientDiagnosis(int $patientId, string $mrn, string $site, int $diagnosisSubcodeId, DateTime $diagnosisDate, string $user): PatientDiagnosis
    {
       $insertedId = DiagnosisAccess::insertPatientDiagnosis($patientId, $mrn, $site, $diagnosisSubcodeId, $diagnosisDate, $user);

       return DiagnosisAccess::getDiagnosisById($insertedId);
    }

    public static function updatePatientDiagnosis(int $patientDiagnosisId, int $diagnosisId, DateTime $diagnosisDate, string $status, string $user): PatientDiagnosis
    {
        $updatedId = DiagnosisAccess::updatePatientDiagnosis($patientDiagnosisId, $diagnosisId, $diagnosisDate, $status, $user);

        return DiagnosisAccess::getDiagnosisById($updatedId);
    }

    /**
     *
     * @return Diagnosis[]
     */
    public static function getDiagnosisCodeList(?string $filter = null): array
    {
        return DiagnosisAccess::getSubcodeList($filter);
    }

    /**
     *
     * @return Diagnosis[]
     */
    public static function getUsedDiagnosisCodeList(): array
    {
        return DiagnosisAccess::getUsedSubCodeList();
    }

    /**
     *
     * @return PatientDiagnosis[]
     */
    public static function getDiagnosisListForPatient(int $patientId): array
    {
        return DiagnosisAccess::getDiagnosisListForPatient($patientId);
    }

}
