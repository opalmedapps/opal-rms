// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

angular.module('vwr').component('registerSMSModal',
{
        templateUrl: 'VirtualWaitingRoom/js/vwr/templates/registerSMSModal.htm',
        controller: registerSMSModalController
});

function registerSMSModalController ($scope,$http,$uibModalInstance,patient)
{
    $scope.patient = patient;

    if(patient.PhoneNumber) {
        $scope.patient.enteredNumber = patient.PhoneNumber.replace(/-/g,"");
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
            url: "php/api/private/v1/patient/updatePhoneNumber",
            method: "POST",
            data: {
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
