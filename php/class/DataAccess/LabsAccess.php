<?php

declare(strict_types=1);

namespace Orms\DataAccess;

use Exception;
use Orms\DataAccess\Database;
use Orms\DateTime;
use Orms\Labs\Model\Labs;

class LabsAccess
{
    /**
     * @return Labs[]
     * @throws Exception
     */
    public static function getLabsListForPatient(string $patientId): array
    {
        $dbh = Database::getOrmsConnection();
        $query = $dbh->prepare("
            SELECT
                ptr.PatientTestResultSerNum AS test_result_id,
                ptr.CollectedDateTime AS specimen_collected_date,
                ptr.ResultDateTime AS component_result_date,
                tge.ExpressionName AS test_group_name,
                tge.TestGroupExpressionSerNum AS test_group_indicator,
                ptr.SequenceNum AS test_component_sequence,
                te.ExpressionName AS test_component_name,
                ptr.TestValueNumeric AS test_value,
                ptr.UnitDescription AS test_units,
                ptr.NormalRangeMax AS max_norm_range,
                ptr.NormalRangeMin AS min_norm_range,
                ptr.AbnormalFlag AS abnormal_flag
            FROM
                `OpalDB`.PatientTestResult ptr,
                `OpalDB`.Patient p,
                `OpalDB`.TestExpression te,
                `OpalDB`.TestGroupExpression tge
            WHERE
                p.PatientSerNum=(
                    SELECT
                    opalPatient.PatientSerNum as opal_pat_ser
                    FROM 
                    `OrmsDatabase`.Patient ormsPatient,
                    `OpalDB`.Patient opalPatient
                    WHERE
                    lower(opalPatient.FirstName)=lower(ormsPatient.FirstName)
                    AND lower(opalPatient.LastName)=lower(ormsPatient.LastName)
                    AND date(ormsPatient.DateOfBirth)=date(opalPatient.DateOfBirth)
                    and ormsPatient.PatientSerNum= :patientId
                )
                AND p.PatientSerNum=ptr.PatientSerNum
                AND ptr.TestExpressionSerNum=te.TestExpressionSerNum
                AND tge.TestGroupExpressionSerNum=ptr.TestGroupExpressionSerNum
                ORDER BY component_result_date, test_group_indicator, test_component_sequence
            ;
        ");
        $query->execute([":patientId" => $patientId]);

        return array_map(function($row) {
            return new Labs(
                test_result_id:            (int) $row["test_result_id"],
                specimen_collected_date: new \DateTime($row["specimen_collected_date"]),
                component_result_date:   new \DateTime($row["component_result_date"]),
                test_group_name:                 $row["test_group_name"],
                test_component_sequence:   (int) $row["test_component_sequence"],
                test_component_name:             $row["test_component_name"],
                test_value:                      $row["test_value"],
                test_units:                      $row["test_units"],
                max_norm_range:                  $row["max_norm_range"],
                min_norm_range:                  $row["min_norm_range"],
                abnormal_flag:                   $row["abnormal_flag"],
            );
        }, $query->fetchAll());
    }
}
