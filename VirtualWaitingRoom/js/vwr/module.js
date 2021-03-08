//main controller
var myApp = angular.module('vwr', ['checklist-model','firebase','ui.bootstrap','ui.select','ngAnimate','ngMaterial','highcharts-ng','ngCookies','ngTable']);

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
        let appointments = inputs[1];
        let fbArray = inputs[2];
        let pageSettings = inputs[3];

        //filter by selected RowTypes
        out = input.filter( x => pageSettings.SelectedRowTypes[x.RowType] === true);

        //filter by selected resources
        out = out.filter( x => resources.includes(x.ResourceName));

        //go through the array of data and perform the operation of figuring out if the patient is assigned to a selected Resource
        // angular.forEach(input, function(patient)
        // {
        //     var store = 0;

        //     for(var currentResource = 0; currentResource < resources.length; currentResource++)
        //     {
        //         if(patient.ResourceName == resources[currentResource]['Name']) {store = 1;}
        //     }

        //     for(var currentAppointment = 0; currentAppointment < appointments.length; currentAppointment++)
        //     {
        //         if(patient.AppointmentName == appointments[currentAppointment]['Name']) {store = 1;}
        //     }

        //     //additionally, check if the patient has the CheckedOut status in firebase
        //     //if they do, filter them
        //     //if(fbArray.hasOwnProperty(patient.Identifier))
        //     //{
        //     //    if(
        //     //        fbArray[patient.Identifier].PatientStatus === 'CheckedOut'
        //     //        && !pageSettings.ShowCheckedOutAppointments
        //     //    ) {store = 0;}
        //     //}

        //     if(store == 1) {out.push(patient);}
        // });

        return out;
    }
});
