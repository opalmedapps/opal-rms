var myApp = angular.module('index', ['checklist-model', 'ui.bootstrap', 'ui.filters','ngAnimate','ngMaterial','dndLists','ngCookies']);

//set up some configs
myApp.config(['$locationProvider','$qProvider',function($locationProvider,$qProvider) {
	$locationProvider.html5Mode({
		enabled: true,
		requireBase: false
	}); //allows correct parsing of french characters from JSON
	$qProvider.errorOnUnhandledRejections(false); //tell modal not to throw errors when we close the modal
}]);

//create a factory with useful function that all controllers can use
angular.module('index').factory('CrossCtrlFuncs',[function ()
{
	return {
		//function to add css properties to each profile depending on which position it will be in the grid
		assignBorderClass: function (profiles)
		{
			var totalPro = profiles.length;
			var lastButtonBorder = '';

			if(totalPro == 0)
			{
				lastButtonBorder = 'button-borderless-left-bottom button-border-thick-right';
			}
			else if(totalPro == 1)
			{
				profiles[totalPro -1].BorderClass = 'button-borderless-left-bottom';
				lastButtonBorder = 'button-borderless-bottom button-border-thick-right';
			}
			else
			{
				angular.forEach(profiles,function (pro,index)
				{
					if((index+1) % 3 == 0) {pro.BorderClass = 'button-borderless-right';}
					else if((index+1) % 3 == 1) {pro.BorderClass = 'button-borderless-left';}
				});

				if(totalPro % 3 == 0)
				{
					profiles[totalPro -1].BorderClass = 'button-borderless-right button-border-thick-bottom';
					profiles[totalPro -2].BorderClass = 'button-border-thick-bottom';
					lastButtonBorder = 'button-borderless-left-bottom button-border-thick-right';
				}
				else if(totalPro % 3 == 1)
				{
					profiles[totalPro -1].BorderClass = 'button-borderless-left-bottom';
					profiles[totalPro -2].BorderClass = 'button-borderless-right button-border-thick-bottom';
					lastButtonBorder = 'button-borderless-bottom button-border-thick-right';
				}
				else if(totalPro % 3 == 2)
				{
					profiles[totalPro -1].BorderClass = 'button-borderless-bottom';
					profiles[totalPro -2].BorderClass = 'button-borderless-left-bottom';
					lastButtonBorder = 'button-borderless-right-bottom';
				}
			}

			return lastButtonBorder;
		}
	};
}]);
