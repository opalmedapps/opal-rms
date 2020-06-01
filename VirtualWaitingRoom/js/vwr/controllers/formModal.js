angular.module('vwr').component('formModal',
{
		templateUrl: 'js/vwr/templates/formModal.htm',
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
