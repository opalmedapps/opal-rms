angular.module('vwr').component('questionnaireModal',{
    templateUrl: 'VirtualWaitingRoom/js/vwr/templates/questionnaireModal.htm',
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
        url: "php/api/private/v1/patient/questionnaire/getQuestionnaires",
        method: "GET",
        params: {patientId: $scope.patient.PatientId}
    }).then(function(response)
    {
        $scope.questionnaireList = response.data.data.map(x => {
            x.name = x.questionnaireName;
            x.selected = false;
            x.reviewed = (!$scope.patient.LastQuestionnaireReview || new Date(x.completionDate) > new Date($scope.patient.LastQuestionnaireReview)) ? "red-circle" : ""

            return x;
        });

        $scope.clinicianQuestionnaireList = response.data.data.filter(x => x.respondentTitle === "Clinician").map(x => {
            x.name = x.questionnaireName;

            return x;
        });
    });

    //Get all the studies in the database
    $http({
        url: "php/api/private/v1/patient/questionnaire/getStudies",
        method: "GET",
        params: {patientId: $scope.patient.PatientId}
    }).then(function(response) {
        $scope.studyList = response.data.data;
    });

    //Get all the possible purpose for a questionnaire
    $http({
        url: "php/api/private/v1/questionnaire/getPurposes",
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
    }

    //Determining if an alert dot should be displayed for purpose buttons.
    $scope.alertDotForPurpose = function(purpose)
    {
        if (purpose.title === 'Research') {
            return $scope.studyList.some(x => $scope.alertDotForStudy(x));
        }
        else {
            if ($scope.questionnaireList.some(x => x.purposeId === purpose.purposeId && x.reviewed === "red-circle")) {
                return true;
            }
            return $scope.clinicianQuestionnaireList.some(x => x.purposeId === purpose.purposeId && x.completed === false);
        }
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
            templateUrl: 'VirtualWaitingRoom/js/vwr/templates/authDialog.htm',
            controller: authDialogController
        })
        .clickOutsideToClose(true);

        $mdDialog.show(answer).then( result => {
            $scope.patient.QStatus = "green-circle";
            $http({
                url: "php/api/private/v1/patient/questionnaire/insertReview",
                method: "POST",
                data: {
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

        //get the answers to the selected questionnaire
        $http({
            url: "php/api/private/v1/patient/questionnaire/getQuestions",
            method: "GET",
            params: {
                patientId:       $scope.patient.PatientId,
                questionnaireId: $scope.selectedQuestionnaire.questionnaireId,
                visualization:   $scope.selectedQuestionnaire.visualization
            }
        }).then(function(response)
        {
            $scope.selectedQuestionnaire.lastDateAnswered = response.data.data.lastDateAnswered;
            $scope.selectedQuestionnaire.questions = response.data.data.questions;

            if($scope.selectedQuestionnaire.visualization === 1)
            {
                $scope.$$postDigest(function()
                {
                    angular.forEach($scope.selectedQuestionnaire.questions,function(chart,index)
                    {
                        //TODO: Generate the Graph using the response data
                    });
                });
            }
        });
    }

    //custom filter that filters the list of shown questionnaires; depends on user search input
    $scope.listFilter = function(type)
    {
        filter = type || '';
        return function(que) {
            return (que.status === 'New' && (filter === false || que.name.toLowerCase().includes(filter.toLowerCase())));
        }
    }

    $scope.clinicalListFilter = function(type)
    {
        filter = type || '';
        return function(que) {
            return (filter === false || que.name.toLowerCase().includes(filter.toLowerCase()));
        }
    }
}
