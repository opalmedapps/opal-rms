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
        $scope.patient.enteredNumber = null;
    }

    $scope.patient.selectedLanguage = patient.LanguagePreference;

    $scope.patient.phoneNumberIsValid = false;

    $scope.validatePhoneNumber = function()
    {
        $scope.patient.phoneNumberIsValid = /(^[0-9]{10}$|^$)/.test($scope.patient.enteredNumber) ? true : false;
    }
    $scope.validatePhoneNumber();

    $scope.addSMS = function()
    {
        $http({
            url: "/php/api/private/v1/patient/updatePhoneNumber",
            method: "GET",
            params: {
                patientId: patient.PatientId,
                phoneNumber: $scope.patient.enteredNumber || null,
                languagePreference: $scope.patient.selectedLanguage,
                specialityGroupId: $scope.patient.SpecialityGroupId
            }
        }).then(function(_)
        {
            $uibModalInstance.close();
        });
    }

    $scope.cancel = function()
    {
        $uibModalInstance.dismiss();
    }

}
