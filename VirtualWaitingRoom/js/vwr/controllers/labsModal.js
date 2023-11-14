angular.module('vwr').component('labModal',{
    templateUrl: 'VirtualWaitingRoom/js/vwr/templates/labModal.htm',
    controller: labsModalController
});

function labsModalController($scope,$http,$mdDialog,NgTableParams,patient)
{
    $scope.patient = patient;

    $scope.patientLabs = [];
    $scope.sortType = 'component_result_date'; // set the default sort type
    $scope.sortReverse = true; // set the default sort order

    loadPatientLabs();

    function stripFlag(str) {
        return str.replace(/\s+/g, ''); 
    }

    function toCamelCase(str) {
        return str
            .toLowerCase()
            .split(' ')
            .map(function(word) {
                return word.charAt(0).toUpperCase() + word.slice(1);
            })
            .join(' ');
    }

    function formatLabDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) 
               + ' ' 
               + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    function formatLabData(labs) {
        return labs.map(function(lab) {
            // Keep the original Date object for sorting
            lab.specimen_collected_datetime = new Date(lab.specimen_collected_date.date);

            // Add a new property for the formatted date string
            lab.specimen_collected_date_formatted = formatLabDate(lab.specimen_collected_datetime);

            lab.test_group_name = toCamelCase(lab.test_group_name); // Camel case conversion
            lab.test_component_name = toCamelCase(lab.test_component_name); // Camel case conversion
            lab.abnormal_flag = stripFlag(lab.abnormal_flag); //  Strip extra whitespace
            return lab;
        });
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
            $scope.patientLabsList = formatLabData(res.data.data); // Apply formatting to the data

        }).catch(error => {
            console.error('Error fetching lab data:', error);
        });
    }
}
