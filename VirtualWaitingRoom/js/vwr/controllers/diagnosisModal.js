angular.module('vwr').component('diagnosisModal',{
    templateUrl: 'js/vwr/templates/diagnosisModal.htm',
    controller: diagnosisModalController
});

function diagnosisModalController($scope,$http,NgTableParams,patient)
{
    $scope.patient = patient;

    $scope.patientDiagnosis = [];

    $scope.userSelectedDate = null;

    loadPatientDiagnosis();

    $scope.searchList = function(searchText)
    {
        if(searchText === "") {
            return [];
        }

        return getDiagnosisCodes(searchText);
    }

    $scope.addPatientDiagnosis = function(diag)
    {
        $http({
            url: "./php/diagnosis/insertPatientDiagnosis.php",
            method: "GET",
            params: {
                patientId: patient.PatientId,
                diagnosisId: diag.id,
                diagnosisDate: (new Date($scope.userSelectedDate)).toLocaleDateString()
            }
        })
        .then( _ => loadPatientDiagnosis());
    }

    $scope.deletePatientDiagnosis = function(patientDiagnosis)
    {
        $http({
            url: "./php/diagnosis/updatePatientDiagnosis.php",
            method: "GET",
            params: {
                patientId: patientDiagnosis.patientId,
                patientDiagnosisId: patientDiagnosis.id,
                diagnosisId: patientDiagnosis.diagnosis.id,
                diagnosisDate: patientDiagnosis.diagnosisDate,
                status: "Deleted"
            }
        })
        .then( _ => loadPatientDiagnosis());
    }

    function getDiagnosisCodes(filter)
    {
        return $http({
            url: "php/diagnosis/getDiagnosisCodeList.php",
            method: "GET",
            params: {
                filter: filter
            }
        }).then(x => x.data);
    }

    function loadPatientDiagnosis()
    {
        $http({
            url: "./php/diagnosis/getPatientDiagnosisList.php",
            method: "GET",
            params: {
                patientId: patient.PatientId
            }
        })
        .then(res => {
            $scope.patientDiagnosisList = new NgTableParams({
                count: 10
            },{
                counts: [],
                dataset: res.data
            });
        });
    }
}
