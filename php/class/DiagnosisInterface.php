<?php declare(strict_types = 1);

namespace Orms;

use Orms\Diagnosis\Diagnosis;
use Orms\Diagnosis\PatientDiagnosis;
use Orms\DateTime;
use PDOException;

class DiagnosisInterface
{
    static function insertPatientDiagnosis(int $patientId,int $diagnosisSubcodeId,DateTime $diagnosisDate): PatientDiagnosis
    {
       $insertedId = PatientDiagnosis::insertPatientDiagnosis($patientId,$diagnosisSubcodeId,$diagnosisDate);

       return PatientDiagnosis::getDiagnosisById($insertedId);
    }

    static function updatePatientDiagnosis(int $patientDiagnosisId,int $diagnosisId,DateTime $diagnosisDate,string $status): PatientDiagnosis
    {
        $updatedId = PatientDiagnosis::updatePatientDiagnosis($patientDiagnosisId,$diagnosisId,$diagnosisDate,$status);

        return PatientDiagnosis::getDiagnosisById($updatedId);
    }

    /**
     *
     * @return Diagnosis[]
     * @throws PDOException
     */
    static function getDiagnosisCodeList(?string $filter = NULL): array
    {
        return Diagnosis::getSubcodeList($filter);
    }

    /**
     *
     * @return PatientDiagnosis[]
     * @throws PDOException
     */
    static function getDiagnosisListForPatient(int $patientId): array
    {
        return PatientDiagnosis::getDiagnosisListForPatient($patientId);
    }

}

?>
