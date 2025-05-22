// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

var myApp = angular.module('screen', ['ngAudio', 'vwr.config']);

myApp.config(["$locationProvider","$qProvider",function($locationProvider,$qProvider) {
    $locationProvider.html5Mode({
        enabled: true,
        requireBase: false
    }); //allows correct parsing of french characters from JSON
    $qProvider.errorOnUnhandledRejections(false); //tell modal not to throw errors when we close the modal
}]);

//checks how long the patient has been on the screen
//new screen entries should be marked for 18 seconds
//entries should stay up for 200 seconds
myApp.filter("timeFilter",function()
{
    return function(inputs)
    {
        let time = new Date().getTime();

        return inputs?.map(x => {
            x.newEntry = (x.Timestamp + 18000) ? true : false;
            return x;
        }).filter(x => time <= x.Timestamp + 200000);
    }
});
