angular.module('vwr').component('questionnaireModal',{
    templateUrl: 'js/vwr/templates/questionnaireModal.htm',
    controller: questionnaireModalController
});

function questionnaireModalController($scope,$http,$mdDialog,$filter,patient)
{
    $scope.patient = patient;
    $scope.questionnaireList = [];

    $scope.selectedQuestionnaire; //initialize variable so we know it exists
    $scope.questionnaires = [];

    $scope.purposeList = [];
    $scope.studyList = [];
    $scope.purposeSelected = '';
    $scope.studySelected = '';
    $scope.clinicianQuestionnaireList = [];

    //Get all questionnaires answered by the input patient
    $http({
        url: "./php/questionnaire/getQuestionnaires.php",
        method: "GET",
        params: {patientId: $scope.patient.PatientId}
    }).then(function(response)
    {
        $scope.questionnaireList = response.data.data;

        angular.forEach($scope.questionnaireList, function(val)
        {
            val.Name = val.QuestionnaireName_EN;
            val.Selected = false;
            val.reviewed = (val.CompletionDate > $scope.patient.LastQuestionnaireReview) ? "red-circle" : ""
        });
    });

    //Get all clinician questionnaires
    $http({
        url: "./php/questionnaire/getClinicianQuestionnaires.php",
        method: "GET",
        params: {patientId: $scope.patient.PatientId}
    }).then(function(response) {
        $scope.clinicianQuestionnaireList = response.data.data;
    });

    //Get all the studies in the database
    $http({
        url: "./php/questionnaire/getStudiesForPatient.php",
        method: "GET",
        params: {patientId: $scope.patient.PatientId}
    }).then(function(response) {
        $scope.studyList = response.data.data;
    });

    //Get all the possible purpose for a questionnaire
    $http({
        url: "./php/questionnaire/getQuestionnairePurposes.php",
        method: "GET"
    }).then(function(response) {
        $scope.purposeList = response.data.data;
    });

    //check if the input questionnaire is a questionnaire for the selected study
    $scope.checkStudy = function(questionnaire)
    {
        if($scope.purposeSelected.title !== 'Research') {
            return true;
        }
        else {
            return questionnaire.studyIdList.includes($scope.studySelected.studyId);
        }
    };

    //update the selected study
    $scope.updateStudy = function(study) {
        $scope.studySelected = study;
    };

    //update the selected purpose and get the alert dot for the questionnaire answered by clinician
    $scope.updatePurpose = function(purpose) {
        $scope.purposeSelected = purpose;
    };

    //Undo the selected information;
    $scope.back = function()
    {
        $scope.selectedQuestionnaire = null;
        $scope.selectedQuestionnaireIsChart = false;
        $scope.selectedQuestionnaireIsNonChart = false;

        if($scope.purposeSelected.title === 'Research' && $scope.studySelected !== '') {
            $scope.studySelected = '';
        }
        else {
            $scope.purposeSelected = '';
        }
    }

    $scope.changeIndexGroup = function()
    {
        $scope.selectedQuestionnaire = null;
        $scope.selectedQuestionnaireIsChart = false;
        $scope.selectedQuestionnaireIsNonChart = false;
    }

    //Determining if an alert dot should be displayed for purpose buttons.
    $scope.alertDotForPurpose = function(purpose)
    {
        if($scope.questionnaireList.some(x => x.PurposeId === purpose.purposeId && x.reviewed === "red-circle")) {
            return true;
        }

        if(purpose.title !== 'Research') {
            return $scope.clinicianQuestionnaireList.some(x => x.PurposeId === purpose.purposeId && x.completed === false);
        }

        return $scope.studyList.some(x => $scope.alertDotForStudy(x));
    }

    //Determining if an alert dot should be displayed for study buttons.
    $scope.alertDotForStudy = function(study)
    {
        if($scope.questionnaireList.some(x => x.studyIdList.includes(study.studyId) && x.reviewed === "red-circle")) {
            return true;
        }
        return $scope.clinicianQuestionnaireList.some(x => x.studyIdList.includes(study.studyId) && x.completed === false);
    }

    //Display the questionnaire if it's completed, If a questionnaire answered by clinician is incomplete, open edit modal.
    $scope.displayClinicalQuestionnaire = function(questionnaire)
    {
        // if the questionnaire is completed, display it
        if (questionnaire.completed) {
            $scope.displayChart(questionnaire);
        }
        // if not, open edit modal TODO : create edit modal to complete clinician questionnaire.
    }

    $scope.markAsReviewed = function()
    {
        let answer = $mdDialog.confirm(
        {
            templateUrl: './js/vwr/templates/authDialog.htm',
            controller: authDialogController
        })
        .clickOutsideToClose(true);

        $mdDialog.show(answer).then( result => {
            $scope.patient.QStatus = "green-circle";
            $http({
                url: "./php/questionnaire/insertQuestionnaireReview.php",
                method: "GET",
                params: {
                    user: result,
                    patientId: $scope.patient.PatientId,
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
                params: {
                    patientId:       $scope.patient.PatientId,
                    questionnaireId: $scope.selectedQuestionnaire.QuestionnaireId
                }
            }).then(function (response) {
                    $scope.selectedQuestionnaire.questions = response.data;
                    //Slider questions werent displaying so on request I'm adding a hotfix
                    //The slider questions were not being displayed in charts because
                    //charts doesnt like strings, and we have to have 0  & 10 be strings for some reason, so I'm manually removing them here.
                    //I think this goes without saying, but this requires a more long term solution in the future...

                    //outer for loop iterates over each chart
                    angular.forEach($scope.selectedQuestionnaire.questions.qData,function(val,key)
                    {    //inner loop iterates over each data point within a given chart
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
                url: "./php/questionnaire/reportDisplayNonChart.php",
                method: "GET",
                params: {
                    patientId:        $scope.patient.PatientId,
                    questionnaireId:  $scope.selectedQuestionnaire.QuestionnaireId
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
            return (que.Status === 'New' && (filter === false || que.Name.toLowerCase().includes(filter.toLowerCase())));
        }
    }

    $scope.clinicalListFilter = function(type)
    {
        filter = type || '';
        return function(que) {
            return (filter === false || que.Name.toLowerCase().includes(filter.toLowerCase()));
        }
    }
}
