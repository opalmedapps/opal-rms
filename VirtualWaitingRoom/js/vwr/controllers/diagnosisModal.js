angular.module('vwr').component('diagnosisModal',{
    templateUrl: 'js/vwr/templates/diagnosisModal.htm',
    controller: diagnosisModalController
});

function diagnosisModalController($scope,$http,$mdDialog,$filter,patient,diagnosisList)
{
    $scope.patient = patient;

    $scope.patientDiagnosis = [];

    loadPatientDiagnosis();

    $scope.searchList = function(searchText)
    {
        if(searchText === "") {
            return diagnosisList
        }

        return diagnosisList.filter(x => x.Subcode.includes(searchText) || x.SubcodeDescription.includes(searchText));
    }

    $scope.addDiagnosis = function(diag)
    {
        $http({
            url: "./php/diagnosis/insertDiagnosis.php",
            method: "GET",
            params: {
                patientId: patient.PatientId,
                diagnosisId: diag.DiagnosisSubcodeId,
                diagnosisDate: null
            }
        })
        .then( _ => loadPatientDiagnosis());

        console.log(diag);
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
            $scope.patientDiagnosisList = res.data;
        });
    }

    // $http({
    //     url: "./php/diagnosis/getPatientDiagnosis.php",
    //     method: "GET",
    //     params: {patientId: $scope.patient.PatientIdRVH}
    // }).then(function (response)
    // {
    //     $scope.patientDiagnosis = response.data;
    // });

    // function openAddDiagnosisDialog(data)
    // {
    //     let answer = $mdDialog.confirm(
    //     {
    //         templateUrl: './js/vwr/templates/editDiagnosis.htm',
    //         controller: function($scope)
    //         {
    //             $scope.page = data;

    //             console.log($scope.page);
    //             $scope.save = async function()
    //             {
    //                 let result = await $http({
    //                     url: "./php/diagnosis/insertDiagnosis.php",
    //                     method: "POST",
    //                     data: {
    //                         patientId: patient.PatientIdRVH,
    //                         diagnosisSerNum: $scope.page.diagnosisSerNum,
    //                         diagnosisDescId: $scope.page.diagnosisDescId
    //                     }
    //                 })
    //                 .then( _ => true)
    //                 .catch( _ => false);

    //                 if(resultAdd === true) {
    //                     $mdDialog.hide($scope.page.codeDiagnosis);
    //                 }
    //                 else {
    //                     $scope.page.message = "Error!";
    //                 }
    //             }
    //         }

    //     })
    //     .ariaLabel('Diagnosis Dialog')
    //     .clickOutsideToClose(true);

    //     $mdDialog.show(answer).then( () => {});
    // }

    // $scope.editDiagnosis = function(diagnosis)
    // {
    //     let data = {
    //         groupCode:              diagnosis.groupCode ?? "",
    //         diagnosisCode:          diagnosis.diagnosisCode ?? "",
    //         description:            diagnosis.description ?? "",
    //         diagnosisSerNum:        diagnosis.diagnosisSerNum,
    //         diagnosisDescId:        diagnosis.diagnosisDescId,
    //         diagnosisCodeList:      $scope.diagnosisCodeList,
    //         message:                ""
    //     }

    //     openAddDiagnosisDialog(data);
    // }

    // $scope.addDiagnosis = function()
    // {
    //     let data = {
    //         groupCode:              "",
    //         diagnosisCode:          "",
    //         description:            "",
    //         diagnosisSerNum:        "",
    //         diagnosisDescId:        "",
    //         diagnosisCodeList:      $scope.diagnosisCodeList,
    //         message:                ""
    //     };

    //     openAddDiagnosisDialog(data);
    // }
}
