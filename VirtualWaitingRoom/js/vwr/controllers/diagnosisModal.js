angular.module('vwr').component('diagnosisModal',{
    templateUrl: 'VirtualWaitingRoom/js/vwr/templates/diagnosisModal.htm',
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
                url: "php/api/private/v1/patient/diagnosis/insertPatientDiagnosis",
                method: "POST",
                data: {
                    patientId: patient.PatientId,
                    mrn: patient.Mrn,
                    site: patient.Site,
                    diagnosisId: diag.id,
                    diagnosisDate: (new Date($scope.userSelectedDate)).toLocaleDateString(),
                    user: username
                }
            })
            .then( _ => loadPatientDiagnosis())
        }
        func();
        // authenticateUser(func);
    }

    $scope.deletePatientDiagnosis = function(patientDiagnosis)
    {
        let func = function(username)
        {
            $http({
                url: "php/api/private/v1/patient/diagnosis/updatePatientDiagnosis",
                method: "POST",
                data: {
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
        func();
        // authenticateUser(func);
    }

    $scope.addAnimationIfOverflowed = function(event)
    {
        text = angular.element(event.currentTarget)[0].parentElement;
        row = text.parentElement;

        if(text.offsetWidth >= row.offsetWidth) {
            text.classList.add("text-scroll");
        }
    }

    $scope.removeAnimation = function(event)
    {
        angular.element(event.currentTarget)[0].classList.remove("test-scroll");
    }

    function getDiagnosisCodes(filter)
    {
        return $http({
            url: "php/api/private/v1/diagnosis/getCodes",
            method: "GET",
            params: {
                filter: filter
            }
        }).then(x => x.data.data);
    }

    function loadPatientDiagnosis()
    {
        $http({
            url: "php/api/private/v1/patient/diagnosis/getPatientDiagnosisList",
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
                dataset: res.data.data
            });
        });
    }

    function authenticateUser(func)
    {
        let answer = $mdDialog.confirm(
        {
            templateUrl: 'VirtualWaitingRoom/js/vwr/templates/authDialog.htm',
            controller: authDialogController
        })
        .clickOutsideToClose(true);

        $mdDialog.show(answer).then( username => func(username));
    }
}
