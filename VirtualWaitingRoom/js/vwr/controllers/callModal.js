//=============================================================================
// Room selection modal controller
//=============================================================================
angular.module('vwr').component('callModal',
{
        templateUrl: 'js/vwr/templates/callModal.htm',
        controller: callModalController
});

function callModalController($scope,$http,$uibModalInstance,$filter,selectedLocations)
{
    $scope.selected = {Name: ''}; //stores the selected call location

    $scope.occupyingIds = []; //contains a list of patient ids for patients who must be checked out of the exam room they're in

    //seperate exam rooms and other rooms since only exam rooms have an occupation limit
    let examRooms = $filter('filter')(selectedLocations,{Type: "ExamRoom"});
    let venueRooms = $filter('filter')(selectedLocations,{Type: "!ExamRoom"});

    //see which rooms are occupied
    //however, venues cannot be occupied by definition (since they have space for many people) so we have to exclude them from the check and then add them back
    $http({
        url: "/php/api/private/v1/vwr/location/getOccupants",
        method: "GET",
        params: {"examRooms[]": examRooms.map(x => x.Name)}
    }).then(function (response)
    {
        $scope.callDestinations = response.data.data;

        //add the types back for exam rooms
        angular.forEach($scope.callDestinations,function(destination)
        {
            destination.Type = "ExamRoom";
        });

        //add the venues back
        angular.forEach(venueRooms,function (venue)
        {
            $scope.callDestinations.push(
            {
                Name: venue.Name,
                ArrivalDateTime: '',
                PatientId: 'Nobody',
                Type: venue.Type
            });
        });
    });

    $scope.accept = function()
    {
        $uibModalInstance.close({'selectedLocation': selectedLocations.find(x => x.Name === $scope.selected.Name), 'occupyingIds': $scope.occupyingIds});
    };

    $scope.cancel = function()
    {
        $uibModalInstance.dismiss('cancel');
    };

    //================================================================
    //see which action to take depending on the occupancy of the room
    //================================================================
    $scope.checkRoomConditions = function(destination)
    {
        if(destination.PatientId === "Nobody") {
            $scope.selected.Name = destination.Name;
        }
        else {
            //if the user decides to free the room, we need to return the occupying patient so that he can be removed from firebase and have his location updated

            $scope.occupyingIds.push({PatientId: destination.PatientId});
            destination.PatientId = 'Nobody';
        }
    }
}
