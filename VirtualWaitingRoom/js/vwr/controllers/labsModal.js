angular.module('vwr').component('labModal',{
    templateUrl: 'VirtualWaitingRoom/js/vwr/templates/labModal.htm',
    controller: labsModalController
});

function labsModalController($scope,$http,$mdDialog,NgTableParams,patient)
{
    $scope.patient = patient;

    $scope.patientLabs = [];

    loadPatientLabs();


    // $scope.addAnimationIfOverflowed = function(event)
    // {
    //     text = angular.element(event.currentTarget)[0].parentElement;
    //     row = text.parentElement;

    //     if(text.offsetWidth >= row.offsetWidth) {
    //         text.classList.add("text-scroll");
    //     }
    // }

    // $scope.removeAnimation = function(event)
    // {
    //     angular.element(event.currentTarget)[0].classList.remove("test-scroll");
    // }

    function formatLabDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) 
               + ' ' 
               + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    // fetch patient results using federated tables in database
    function loadPatientLabs()
    {
        $http({
            url: "php/api/private/v1/labs/getPatientLabsList",
            method: "GET",
            params: {
                patientId: patient.PatientId
            }
        })
        .then(res => {
            $scope.patientLabsList = res.data.data.map(function(lab) {
                lab.specimen_collected_date = formatLabDate(lab.specimen_collected_date.date); // Format the date
                return lab;
            });
        }).catch(error => {
            console.error('Error fetching lab data:', error);
        });
    }

    // function authenticateUser(func)
    // {
    //     let answer = $mdDialog.confirm(
    //     {
    //         templateUrl: 'VirtualWaitingRoom/js/vwr/templates/authDialog.htm',
    //         controller: authDialogController
    //     })
    //     .clickOutsideToClose(true);

    //     $mdDialog.show(answer).then( username => func(username));
    // }
}
