angular.module('vwr').component('registerSMSModal',
{
        templateUrl: 'js/vwr/templates/registerSMSModal.htm',
        controller: registerSMSModalController
});

function registerSMSModalController ($scope,$http,$uibModalInstance,patient)
{
    $scope.patient = patient;
    if(patient.SMSAlertNum) {
        $scope.patient.enteredNumber = patient.SMSAlertNum.replace(/-/g,"");
    }
    else {
        $scope.patient.enteredNumber = "";
    }
    $scope.patient.selectedLanguage = "French";

    $scope.patient.phoneNumberIsValid = false;

    $scope.validatePhoneNumber = function()
    {
        $scope.patient.phoneNumberIsValid = /(^[0-9]{10}$|^$)/.test($scope.patient.enteredNumber) ? true : false;
    }
    $scope.validatePhoneNumber();

    $scope.addSMS = function()
    {
        $http({
            url: "php/setPatientMobileNumber.php",
            method: "GET",
            params: {
                patientIdRVH: patient.PatientIdRVH,
                patientIdMGH: patient.PatientIdMGH,
                phoneNumber: $scope.patient.enteredNumber,
                language: $scope.patient.selectedLanguage
            }
        }).then(function (response)
        {
            $uibModalInstance.close();
        });
    }

    $scope.cancel = function()
    {
        $uibModalInstance.dismiss();
    }

}

