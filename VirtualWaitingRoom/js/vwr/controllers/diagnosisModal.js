angular.module('vwr').component('diagnosisModal',{
    templateUrl: 'js/vwr/templates/diagnosisModal.htm',
    controller: diagnosisModalController
});

function diagnosisModalController($scope,$http,$mdDialog,NgTableParams,patient)
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
        let func = function(username)
        {
            return $http({
                url: "./php/diagnosis/insertPatientDiagnosis.php",
                method: "GET",
                params: {
                    patientId: patient.PatientId,
                    diagnosisId: diag.id,
                    diagnosisDate: (new Date($scope.userSelectedDate)).toLocaleDateString(),
                    user: username
                }
            })
            .then( _ => loadPatientDiagnosis())
        }

        authenticateUser(func);
    }

    $scope.deletePatientDiagnosis = function(patientDiagnosis)
    {
        let func = function(username)
        {
            $http({
                url: "./php/diagnosis/updatePatientDiagnosis.php",
                method: "GET",
                params: {
                    patientId: patientDiagnosis.patientId,
                    patientDiagnosisId: patientDiagnosis.id,
                    diagnosisId: patientDiagnosis.diagnosis.id,
                    diagnosisDate: patientDiagnosis.diagnosisDate,
                    status: "Deleted",
                    user: username
                }
            })
            .then( _ => loadPatientDiagnosis());
        }

        authenticateUser(func);
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

    function authenticateUser(func)
    {
        let answer = $mdDialog.confirm(
        {
            templateUrl: './js/vwr/templates/authDialog.htm',
            controller: authDialogController
        })
        .clickOutsideToClose(true);

        $mdDialog.show(answer).then( username => func(username));
    }
}
