// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

angular.module('index').component('editor',{
    templateUrl: 'VirtualWaitingRoom/js/index/templates/profileEditor.htm',
    controller: profileEditorController
});

function profileEditorController($scope,$http,$filter,CrossCtrlFuncs)
{
    $scope.selectedTab = 1; //current tab the user is in
    $scope.givenOptions = {}; //list of all resources/locations user can select
    $scope.possibleColumns =  //get the possible columns that can be selected
    {
        chosen: [],
        possible: [],
        focusedColumn: {}
    };

    $scope.errorMessage = ''; //contains the latest error that prevented profile creation/update

    //reset the currently selected profile
    $scope.resetSelectedProfile = function()
    {
        $scope.selectedProfile = //currently selected profile
        {
            index: '',
            data:
            {
                Category: $scope.group,
                ColumnsDisplayed: [],
                ExamRooms: [],
                IntermediateVenues: [],
                Locations: [],
                ProfileId: [],
                ProfileSer: -1,
                Resources: [],
                Speciality: $scope.speciality,
                ClinicHub: $scope.clinicHub
            }
        };

        $scope.errorMessage = '';
    }

    //reset the currently chosen columns
    $scope.resetSelectedColumns = function()
    {
        angular.forEach($scope.possibleColumns.chosen,function (col)
        {
            $scope.possibleColumns.possible.push(col);
        });

        $scope.possibleColumns.chosen = [];

        $scope.possibleColumns.possible = $filter('orderBy')($scope.possibleColumns.possible,'+ColumnName');
    }

    //toogle if we show the description for a column
    $scope.toggleColumnDescription = function(col)
    {
        if(col === $scope.possibleColumns.focusedColumn) {$scope.possibleColumns.focusedColumn = {};}
        else {$scope.possibleColumns.focusedColumn = col;}
    }

    //listen for changes in the profile list
    $scope.$on('profileListUpdated',function(event,data)
    {
        $scope.selectedTab = 1;
        $scope.group = data.group;
        $scope.speciality = data.speciality;
        $scope.clinicHub = data.clinicHub;

        $scope.profiles = data.profiles;
        $scope.lastButtonBorder = CrossCtrlFuncs.assignBorderClass($scope.profiles);

        $scope.resetSelectedProfile();

        //at this point, we have everything we need to get all the options the user can select
        $http({
            url: "php/api/private/v1/appointment/getClinics",
            method: "GET",
            params: {
                speciality: $scope.speciality,
                clinicHub: $scope.clinicHub
            }
        }).then(function(response) {
            $scope.givenOptions.Resources = response.data.data.map(x => ({Name: x.description,Type: "Resource"}));

            $http({
                url: "php/api/private/v1/hospital/getRooms",
                method: "GET",
                params: {
                    clinicHub: $scope.clinicHub
                }
            }).then(function(response) {
                $scope.givenOptions.Locations = response.data.data;
            });
        });

        //also get the columns that the user can select
        $http({
            url: "php/api/private/v1/profile/getColumns",
            method: "GET"
        }).then(function (response)
        {
            $scope.possibleColumns.possible = [];
            $scope.possibleColumns.chosen = [];
            $scope.possibleColumns.focusedColumn = {};

            $scope.possibleColumns.possible = response.data.data;
        });

        //also get a list of all profile Ids in the db
        $http({
            url: "php/api/private/v1/profile/getList",
            method: "GET"
        }).then(function (response)
        {
            $scope.allProfiles = response.data.data;
        });


    });

    //return the class to be used in the DOM
    $scope.checkBorderClass = function (button)
    {
        if(button === 'last') {return $scope.lastButtonBorder;}
        else {return button.BorderClass;}
    }

    //mark the user selected profile and get its data
    $scope.selectProfile = function (index)
    {
        $scope.selectedProfile.index = index;

        $scope.fetchProfileDetails(index);

        $scope.selectedTab = 2;
    }

    //fetch profile details from the db
    $scope.fetchProfileDetails = function (index)
    {
        if(index === -1)
        {
            $scope.resetSelectedProfile();
            $scope.resetSelectedColumns();
            $scope.selectedProfile.index = -1;
        }
        else
        {
            //get the selected profile info
            $http({
                url: "php/api/private/v1/profile/getProfile",
                method: "GET",
                params: {
                    profileId: $scope.profiles[index].ProfileId,
                    clinicHubId: $scope.clinicHub
                }
            }).then(function(response)
            {
                $scope.selectedProfile.data = response.data.data;

                //after the profile has finished loading, process the columns in the profile
                $scope.resetSelectedColumns();

                angular.forEach($scope.selectedProfile.data.ColumnsDisplayed,function (col)
                {
                    for(var i = 0; i < $scope.possibleColumns.possible.length; i++)
                    {
                        if(col.ColumnName === $scope.possibleColumns.possible[i].ColumnName)
                        {

                            $scope.possibleColumns.chosen.push($scope.possibleColumns.possible.splice(i,1)[0]);
                            break;
                        }
                    }
                });
            });

        }
    }

    $scope.verifyIfIdIsTaken = function (examinedProfile)
    {
        let idAlreadyExists = false;

        angular.forEach($scope.allProfiles,function (pro)
        {
            if(pro.ProfileId === examinedProfile.ProfileId && pro.ProfileSer != examinedProfile.ProfileSer)
            {
                idAlreadyExists = true;
            }
        });

        if(idAlreadyExists) {$scope.errorMessage = "Profile name already in use!";}
        else {$scope.errorMessage = '';}
    }

    $scope.previousTab = function()
    {
        $scope.selectedTab--;
    }

    $scope.nextTab = function()
    {
        $scope.selectedTab++;
    }

    $scope.resetSection = function()
    {
        $scope.resetSelectedProfile();
        $scope.resetSelectedColumns();
        $scope.selectedTab = 1;
    }

    $scope.updateProfile = function()
    {
        let profile = $scope.selectedProfile.data;

        $http({
            url: "php/api/private/v1/profile/updateProfile",
            method: "POST",
            data: {
                profileSer:             profile.ProfileSer,
                profileId:              profile.ProfileId,
                speciality:             profile.Speciality,
                category:               profile.Category,
                options:                profile.Locations.concat(profile.Resources),
                columns:                $scope.possibleColumns.chosen.map(x => x.ColumnName)
            }
        }).then(function(response)
        {
            $scope.selectedTab = 1;

            $scope.$emit('profileUpdated',{});
        });

    }

    $scope.deleteProfile = function()
    {
        $http({
            url: "php/api/private/v1/profile/deleteProfile",
            method: "POST",
            data:{
                profileId: $scope.selectedProfile.data.ProfileId
            }
        }).then(function(response)
        {
            $scope.selectedTab = 1;

            $scope.$emit('profileUpdated',{});
        });
    }

}
