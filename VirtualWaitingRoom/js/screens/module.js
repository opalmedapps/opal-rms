var myApp = angular.module('screen', ['firebase', 'ui.bootstrap','ngAudio']);

myApp.config(['$locationProvider','$qProvider',function($locationProvider,$qProvider) {
	$locationProvider.html5Mode({
		enabled: true,
		requireBase: false
	}); //allows correct parsing of french characters from JSON
	$qProvider.errorOnUnhandledRejections(false); //tell modal not to throw errors when we close the modal
}]);

//checks how long the patient has been on the screen
//new screen entries should be marked for 18 seconds
//entries should stay up for 200 seconds
myApp.filter('timeFilter',function()
{
	return function (inputs)
	{
		var time = new Date();
		time = time.getTime();
		var valids = [];

		angular.forEach(inputs,function (input)
		{
			if(time <= input.Timestamp + 18000) 
			{
				input.newEntry = true;
			}
			else {input.newEntry = false;}

			if(time <= input.Timestamp + 200000) 
			{
				valids.push(input);
			}

		});

		return valids;
	}

});
