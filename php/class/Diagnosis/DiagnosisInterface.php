<?php

declare(strict_types=1);

namespace Orms\Diagnosis;

use Orms\DateTime;
use Orms\Diagnosis\Internal\Diagnosis;
use Orms\Diagnosis\Internal\PatientDiagnosis;
use PDOException;

class DiagnosisInterface
{
    public static function insertPatientDiagnosis(int $patientId, int $diagnosisSubcodeId, DateTime $diagnosisDate, string $user): PatientDiagnosis
    {
       $insertedId = PatientDiagnosis::insertPatientDiagnosis($patientId, $diagnosisSubcodeId, $diagnosisDate, $user);

       return PatientDiagnosis::getDiagnosisById($insertedId);
    }

    public static function updatePatientDiagnosis(int $patientDiagnosisId, int $diagnosisId, DateTime $diagnosisDate, string $status, string $user): PatientDiagnosis
    {
        $updatedId = PatientDiagnosis::updatePatientDiagnosis($patientDiagnosisId, $diagnosisId, $diagnosisDate, $status, $user);

        return PatientDiagnosis::getDiagnosisById($updatedId);
    }

    /**
     *
     * @return Diagnosis[]
     * @throws PDOException
     */
    public static function getDiagnosisCodeList(?string $filter = null): array
    {
        return Diagnosis::getSubcodeList($filter);
    }

    /**
     *
     * @return Diagnosis[]
     * @throws PDOException
     */
    public static function getUsedDiagnosisCodeList(): array
    {
        return Diagnosis::getUsedSubCodeList();
    }

    /**
     *
     * @return PatientDiagnosis[]
     * @throws PDOException
     */
    public static function getDiagnosisListForPatient(int $patientId): array
    {
        return PatientDiagnosis::getDiagnosisListForPatient($patientId);
    }

}
