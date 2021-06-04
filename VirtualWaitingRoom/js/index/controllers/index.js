//controller for the index file
// main controller for page

myApp.controller("indexController",function($scope,$http,$window,$cookies,CrossCtrlFuncs)
{
    $scope.selectedTab = ''; //set the first tab as the default view
    $scope.speciality = $cookies.get("specialityGroupId");
    $scope.clinicHub = $cookies.get("clinicHubId");

    var resetPage = function()
    {
        $scope.userOptions = //stores all options the user has picked up till now
        {
            Page: '', //page that the user wants to go to
            Speciality: $scope.speciality, //speciality the user is in
            Group: '', //group category the user selected
            Profiles: [], //list of all profiles in a selected group
            SelectedProfile: {}, //user sselected profile; used to launch the VWR page
        };
    }
    resetPage();

    //brings to user back to the first tab
    $scope.restartPage = function()
    {
        $scope.selectedTab = 0;
        resetPage();
    }

    //sets a userOptions property to the given value and moves the selected page to the specified one
    $scope.moveToTab = function(property,value,tab)
    {
        if(property != null)
        {
            $scope.userOptions[property] = value;
        }

        var mTab = '';

        if(tab === 'ModeTab') {mTab = 0;}
        else if(tab === 'GroupTab') {mTab = 1;}
        else if(tab === 'ProfileTab') {mTab = 2;}
        else if(tab === 'EditTab') {mTab = 3;}

        $scope.selectedTab = mTab;
    }

    $scope.getProfileList = function()
    {
        //get the list of profiles in this group
        $http({
            url: "php/profile/getProfileList.php",
            method: "GET",
            params:
            {
                category: $scope.userOptions.Group,
                speciality: $scope.userOptions.Speciality
            }
        }).then(function (response)
        {
            $scope.userOptions.Profiles = response.data;

            //put css on each button and also the create profile button
            $scope.lastButtonBorder = CrossCtrlFuncs.assignBorderClass($scope.userOptions.Profiles);
        });
    }

    //return the class to be used in the DOM
    $scope.checkBorderClass = function(button)
    {
        if(button === 'last') {return $scope.lastButtonBorder;}
        else {return button.BorderClass;}
    }

    //tell the profile editor controller that the list of profiles has changed
    $scope.broadcastProfileList = function()
    {
        $scope.$broadcast('profileListUpdated',
        {
            profiles: $scope.userOptions.Profiles,
            group: $scope.userOptions.Group,
            speciality: $scope.userOptions.Speciality,
            clinicHub: $scope.clinicHub
        });
    }

    $scope.$on('profileUpdated',function(event)
    {
        $scope.moveToTab(null,null,'ProfileTab');
    });

    $scope.redirectURL = function(profileId)
    {
        //if user is launching the VWR, then just open the page with the selected profile
        if($scope.userOptions.Page === 'vwr')
        {
            $window.location.href = "./virtualWaitingRoom?profile=" + profileId +"&clinicHub="+ $scope.clinicHub;
        }
        //if the user is opening the screen display, make sure a location was selected
        else if($scope.userOptions.Page)
        {
            var url = "";

            url = url + "./screenDisplay?location=" + $scope.clinicHub;

            $window.location.href = url;
        }
    }
});
