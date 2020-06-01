angular.module('vwr').component('questionnaireModal',{
	templateUrl: 'js/vwr/templates/questionnaireModal.htm',
	controller: questionnaireModalController
});

function questionnaireModalController($scope,$uibModalInstance,$http,patient)
{

	$scope.patient = patient;

	$scope.qqq = [
		{Name: 'a',Type: 1},
		{Name: 'b',Type: 2}
	];

	$scope.loadQuestionnaireData = function()
	{
		console.log(4);
	}

	$http({
		url: "php/getQuestionnaireList.php",
		method: "GET",
		params:
		{
			patientId: $scope.patient.PatientId
		}
	}).then(function (response) 
	{
		console.log(response);
	});

	$scope.selectedQuestionnaire; //initialize variable so we know it exists
	$scope.questionnaires = [];

	//add scoll tracking to the modal to determine when the user has reached the bottom of the modal
	$scope.addScrollFunc = function()
	{
		$(".modal").scroll(function() 
		{
			if($(".modal").scrollTop() + $(".modal").height() == $(".modal")[0].scrollHeight) 
			{
				//console.log("bottom!");
				$scope.$apply();	
			}
		});
	}

	//set which questionnaire to display
	$scope.displayChart = function(que)
	{
		$scope.selectedQuestionnaire = que;
	}
}


