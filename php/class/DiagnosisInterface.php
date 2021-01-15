<?php declare(strict_types = 1);

namespace Orms;

use Orms\Config;
use Orms\Diagnosis\Diagnosis;
use Orms\Diagnosis\PatientDiagnosis;

class DiagnosisInterface
{
    static function insertPatientDiagnosis(int $patientId,int $diagnosisSubcodeId,DateTime $diagnosisDate): PatientDiagnosis
    {
       $insertedId = PatientDiagnosis::insertPatientDiagnosis($patientId,$diagnosisSubcodeId,$diagnosisDate);

       return PatientDiagnosis::getDiagnosisById($insertedId);
    }

    static function updatePatientDiagnosis(int $patientId,int $patientDiagnosisId,int $diagnosisSubcodeId,string $status): void
    {

    }

    static function getDiagnosisCodeList(): array
    {
        return Diagnosis::getSubcodeList();

        //convert the db rows into a more organized form
        // $rows = ArrayUtil::groupArrayByKeyRecursiveKeepKeys($query->fetchAll(),"Chapter","Code");

        // return $rows = array_map(function($x) {
        //     $chFirst = $x[array_key_first($x)][0]; //first diagnosis object for this chapter

        //     return [
        //         "chapter" => $chFirst["Chapter"],
        //         "chapterDescription" => $chFirst["ChapterDescription"],
        //         "codes" => array_map(function($y) {
        //             $coFirst = $y[0]; //first diagnosis object for this code

        //             return [
        //                 "code" => $coFirst["Code"],
        //                 "codeDescription" => $coFirst["CodeDescription"],
        //                 "subcodes" => array_map(function($z) {
        //                     return new Diagnosis(
        //                         (int) $z["DiagnosisSubcodeId"],
        //                         $z["Subcode"],
        //                         $z["SubcodeDescription"],
        //                         $z["Code"],
        //                         $z["Category"],
        //                         $z["CodeDescription"],
        //                         $z["Chapter"],
        //                         $z["ChapterDescription"]
        //                     );
        //                 },$y)
        //             ];
        //         },$x)
        //     ];
        // },$rows);
    }

    static function getDiagnosisListForPatient(int $patientId)
    {
        return PatientDiagnosis::getDiagnosisListForPatient($patientId);
    }

}

?>
