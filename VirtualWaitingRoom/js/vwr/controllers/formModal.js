// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

angular.module('vwr').component('formModal',
{
        templateUrl: 'VirtualWaitingRoom/js/vwr/templates/formModal.htm',
        controller: formModalController
});

function formModalController ($scope,$http,$uibModalInstance,patient)
{
    $scope.patient = patient;

    $scope.accept = function()
    {
        $uibModalInstance.close();
    }

    $scope.cancel = function()
    {
        $uibModalInstance.dismiss();
    }

}
