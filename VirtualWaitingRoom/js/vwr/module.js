// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

//main controller
var myApp = angular.module('vwr', ['checklist-model','firebase','ui.bootstrap','ui.select','ngAnimate','ngMaterial','ngCookies','ngTable','vwr.config']);

//create mock module to define the $rootElement as it will be needed to manually bootstrap the page later
//used right after defining the vwr controller
var mockApp = angular.module('mockApp',['ngAnimate','ngMaterial']).provider({
    $rootElement: function()
    {
        this.$get = function()
        {
            return angular.element('<div ng-app></div>');
        };
    }
}).config(['$locationProvider','$qProvider',function($locationProvider,$qProvider) {
    $locationProvider.html5Mode({
        enabled: true,
        requireBase: false
    }); //allows $location to read url parameters
}]);

//set up some configs
myApp.config(['$locationProvider','$qProvider',function($locationProvider,$qProvider) {
    $locationProvider.html5Mode({
        enabled: true,
        requireBase: false
    }); //allows correct parsing of french characters from JSON
    $qProvider.errorOnUnhandledRejections(false); //tell modal not to throw errors when we close the modal
}]);

// Custom filter for resource selection in the template
myApp.filter('multiResourceFilter', function()
{
    // Create the return function and set the required parameter name to **input**
    return function (input,inputs)
    {
        if(!input) return [];

        let out = [];

        let resources = inputs[0].map( x => x.Name);
        let pageSettings = inputs[1];

        //filter by selected RowTypes
        out = input.filter( x => pageSettings.SelectedRowTypes[x.RowType] === true);

        //filter by selected resources
        out = out.filter( x => resources.includes(x.ResourceName));

        return out;
    }
});
