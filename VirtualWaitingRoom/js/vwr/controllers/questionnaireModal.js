angular.module('vwr').component('questionnaireModal',{
    templateUrl: 'js/vwr/templates/questionnaireModal.htm',
    controller: questionnaireModalController
});

function questionnaireModalController($scope,$uibModalInstance,$http,$mdDialog,$cookies,$filter,patient)
{
    $scope.patient = patient;
    $scope.questionnaireList = [];

    $scope.selectedQuestionnaire; //initialize variable so we know it exists
    $scope.questionnaires = [];

    //***********************************************************
    //questionnaires exist only for RVH patients for now...
    //***********************************************************

    $http({
        url: "./php/questionnaire/getQuestionnaires.php",
        method: "GET",
        params: {Patient_ID: $scope.patient.PatientIdRVH}
    }).then(function (response)
    {
        $scope.questionnaireList = response.data;

        $scope.patient.Age = $scope.questionnaireList[0].Age;
        $scope.patient.Sex = $scope.questionnaireList[0].Sex.substring(0,1);

        angular.forEach($scope.questionnaireList, function(val)
        {
            val.Name = val.QuestionnaireName_EN;
            val.Selected = false;
        });

        //by default, we would select the most recently answered questionnaire
        $scope.displayChart($scope.questionnaireList[0]);
    });


    //add scoll tracking to the modal to determine when the user has reached the bottom of the modal
    /*$scope.addScrollFunc = function()
    {
        $(".modal").scroll(function()
        {
            if($(".modal").scrollTop() + $(".modal").height() == $(".modal")[0].scrollHeight)
            {
                console.log("bottom!");
            }
        });
    }*/

    $scope.markAsReviewed = function()
    {
        let answer = $mdDialog.confirm(
        {
            templateUrl: './js/vwr/templates/authDialog.htm',
            controller: function($scope)
            {
                $scope.page = {
                    username: $cookies.get("lastUsedUsername") ?? "", //if the user has previously authenticated, a cookie with the username should exist
                    password: "",
                    message: ""
                };
                $scope.Title = 'Login to mark as Reviewed';
                $scope.authenticate = async function()
                {
                    let authResult = await $http({
                        url: "./php/authenticateUser.php",
                        method: "POST",
                        data: {
                            username: $scope.page.username,
                            password: $scope.page.password
                        }
                    })
                    .then( response => response.data.valid)
                    .catch( _ => null);

                    $scope.page.password = ""; //clear the password field

                    if(authResult === true)
                    {
                        $cookies.put("lastUsedUsername",$scope.page.username); //store the last authenticated username in case the user is reviewing multiple questionnaires
                        $mdDialog.hide($scope.page.username);
                    }
                    else if(authResult === false) {
                        $scope.page.message = "Invalid username or password!";
                    }
                    else {
                        $scope.page.message = "Error! Please try again later.";
                    }
                }
            }

        })
        .ariaLabel('Auth Dialog')
        .clickOutsideToClose(true);

        $mdDialog.show(answer).then( result => {
            $scope.patient.QStatus = "green-circle";
            $http({
                url: "./php/insertQuestionnaireReview.php",
                method: "GET",
                params: {
                    user: result,
                    patientIdRVH: $scope.patient.PatientIdRVH,
                    patientIdMGH: $scope.patient.PatientIdMGH
                }
            })
            .then( _ => {$scope.patient.LastQuestionnaireReview = $filter("date")(new Date(),"yyyy-MM-dd HH-mm");});
        });
    }


    //set which questionnaire to display
    $scope.displayChart = function(que)
    {
        $scope.selectedQuestionnaire = que;

        $scope.selectedQuestionnaireIsChart = false;
        $scope.selectedQuestionnaireIsNonChart = false;

        //get the answers to the selected questionnaire

        //use if the answers are displayed as a chart
        if($scope.selectedQuestionnaire.Visualization === "1")
        {
            $http({
                url: "./php/questionnaire/reportDisplay.php",
                method: "GET",
                params: {ID: $scope.patient.PatientIdRVH,
                    rptID:$scope.selectedQuestionnaire.QuestionnaireDBSerNum
                }
            }).then(function (response) {
                    $scope.selectedQuestionnaire.questions = response.data;
                    //Slider questions werent displaying so on request I'm adding a hotfix
                    //The slider questions were not being displayed in charts because
                    //charts doesnt like strings, and we have to have 0  & 10 be strings for some reason, so I'm manually removing them here.
                    //I think this goes without saying, but this requires a more long term solution in the future...

                    //outer for loop iterates over each chart
                    angular.forEach($scope.selectedQuestionnaire.questions.qData, function(val, key)
                    {	//inner loop iterates over each data point within a given chart
                        angular.forEach(val.series[0].data, function(innerVal, key){
                            if(typeof innerVal[1] === 'string' || innerVal[1] instanceof String){
                                innerVal[1] = parseInt(innerVal[1].split(" ",1)[0]);
                            }
                        });
                    });
                    $scope.selectedQuestionnaireIsChart = true;
                    //$scope.$apply();

                    $scope.$$postDigest(function()
                    {
                        Highcharts.setOptions($scope.selectedQuestionnaire.questions.langSetting);

                        angular.forEach($scope.selectedQuestionnaire.questions.qData,function(chart,index)
                        {
                            chart.tooltip.formatter = function() {
                                return '<b>Date: </b>' + Highcharts.dateFormat('%Y - %b - %e', new Date(this.x)) +'<br /> <b>' + this.series.name +': </b>'  + this.y;
                            };
                            chart.xAxis.labels.format = "'{value:%Y}'+<br />+{value:%b}'";

                            Highcharts.chart('question'+index,chart)
                        });
                    });
            });
        }
		//use if the answers are displayed as a list
		else if($scope.selectedQuestionnaire.Visualization === "0")
        {
            $http({
                url: "./php/questionnaire/report.php",
                method: "GET",
                params: {ID: $scope.patient.PatientIdRVH,
                    rptID:$scope.selectedQuestionnaire.QuestionnaireDBSerNum
                }
            }).then(function (response) {
                    $scope.selectedQuestionnaire.questions = {
                        lastDateAnswered: $scope.selectedQuestionnaire.CompletionDate,
                        qData: response.data,
                    }

                    $scope.selectedQuestionnaireIsNonChart = true;
            });

        }
        else {console.log("Unknown questionnaire");}
    }

    //custom filter that filters the list of shown questionnaires; depends on user search input
    $scope.listFilter = function(type)
    {
        filter = type || '';
        return function(que) {
            return (que.Status === 'New' && (filter == false || que.Name.toLowerCase().lastIndexOf(filter.toLowerCase()) >= 0));
        }
    }
}
