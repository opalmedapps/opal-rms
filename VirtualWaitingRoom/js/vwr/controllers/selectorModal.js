// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

angular.module('vwr').component('selectorModal',
{
        templateUrl: 'VirtualWaitingRoom/js/vwr/templates/selectorModal.htm',
        controller: selectorModalController
});

function selectorModalController ($scope,$uibModalInstance,$filter,inputs)
{
    //get the list of options were going to display

    $scope.passedData =
    {
        givenOptions: inputs.options,
        selectedOptions: angular.copy(inputs.selectedOptions),
        title: inputs.title
    }

    if(!$scope.passedData.selectedOptions) {$scope.passedData.selectedOptions = [];}

    $scope.checkAllButton = true; //determines if the auto select button select or un-selects options

    //function to check if an option object is contained in the options array
    //returns the index of the object in the array or -1
    //we need this because angular adds a $$hash property to each object, making the javascript comparison always false
    var checkIfOptionIsSelected = function(optionsArr,opt)
    {
        for(var i = 0; i < optionsArr.length; i++)
        {
            if(angular.equals(optionsArr[i],opt)) {return i;}
        }

        return -1;
    }

    //function to check/uncheck all visible options
    $scope.handleVisibles = function()
    {
        var visibleOptions = $filter('filter')($scope.passedData.givenOptions,{Name: $scope.selectionFilter});

        if($scope.checkAllButton == true)
        {
            angular.forEach(visibleOptions,function (opt)
            {
                if(checkIfOptionIsSelected($scope.passedData.selectedOptions,opt) == -1) {$scope.passedData.selectedOptions.push(opt);}
            });
        }
        else
        {
            angular.forEach(visibleOptions,function (opt)
            {
                var index = checkIfOptionIsSelected($scope.passedData.selectedOptions,opt);
                $scope.passedData.selectedOptions.splice(index,1);
            });
        }
    }

    //create a watcher to monitor the if all visble options have been selected
    $scope.$watch(function()
    {
        //we need to see if all visible options are contained in our selected options array
        //so we loop through every visible option
        var visibleOptions = $filter('filter')($scope.passedData.givenOptions,{Name: $scope.selectionFilter});

        if($scope.passedData.selectedOptions.length == 0) {return true;}

        for(var i = 0; i < visibleOptions.length; i++)
        {
            if(checkIfOptionIsSelected($scope.passedData.selectedOptions,visibleOptions[i]) == -1) {return true;}
        }

        return false;
    },function (newValue,oldValue)
    {
        if(newValue) {$scope.checkAllButton = true;}
        else {$scope.checkAllButton = false;}
    });

    $scope.accept = function()
    {
        $uibModalInstance.close($filter('orderBy')($scope.passedData.selectedOptions,'+Name'));
    }

    $scope.cancel = function()
    {
        $uibModalInstance.dismiss();
    }
}
