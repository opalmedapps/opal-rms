//virtualWaitingRoom Controller

myApp.controller("virtualWaitingRoomController",function ($scope,$uibModal,$http,$firebaseObject,$interval,$filter,$mdDialog,$window,ProfileSettings)
{
    //=========================================================================
    // General useful stuff
    //=========================================================================
    //get the profile settings that have already been defined for the page
    $scope.pageSettings = ProfileSettings;

    $scope.screenMessage = "Normal";

    // Get today's date
    var today = new Date();
    var dateToday = today.getDate();
    var monthToday = today.getMonth()+1; //January is 0!
    var yearToday = today.getFullYear();

    var loadHour = today.getHours();

    today = $filter('date')(today,'MM-dd-yyyy');

    // get the time right now and do it on a regular basis so that the
    // right now time is updated continuously
    $interval(function()
    {
        var dateTimeNow = new Date();
        $scope.timeNow = dateTimeNow.getTime();

        //check if the autofetched resources have expired, that is the time went from AM->PM or PM->AM
        var dateNow = dateTimeNow.getDate();
        var hourNow = dateTimeNow.getHours();

        if(
            ((loadHour < 13 && hourNow >= 13) || (loadHour >= 13 && hourNow < 12))
            && ($scope.pageSettings.FetchResourcesFromClinics === 1 || $scope.pageSettings.FetchResourcesFromVenues === 1)
        ) {$scope.screenMessage = "Auto Loaded Resources Expired! Please Reload Page.";}

        //alternatively, if it is 12:01AM then the page has expired and needs to connect to the new day's firebase
        if(dateToday != dateNow)
        {
            $scope.screenMessage = "Page Expired! Please Reload Page.";

        }

    },1000);

    $scope.intervalAlreadySet = 0; //tells us if the $interval that reloads the checkin file has been set
    $scope.patientLoadingEnabled = 0; //indicates if its safe to load the checkin file (ie we are connected to firebase)

    $scope.resourceLoadingEnabled = 0; //indicates if its safe to open the selector modal (ie we retrieved the resources from the db)
    //=========================================================================
    // Get list of checked-in patients - use $interval to request the
    // list regularly (specified in miliseconds)
    //=========================================================================
    $scope.checkinFile = "";

    // Function to grab the list of patients
    var loadPatients = function ()
    {
        if($scope.checkinFile)
        {
            $http.get($scope.checkinFile).then(function(response)
            {
                $scope.checkins = response.data;
            });
        }
        else
        {
            $http({
                url: "php/getCheckinFile.php",
                method: "GET"
            }).then(function(response)
            {
                $scope.checkinFile = response.data.checkinFile;
                $scope.checkinFile = $scope.checkinFile +"_"+ $scope.pageSettings.Speciality;
                loadPatients();

                //also get the opal notification file here
                $scope.opalNotificationUrl = response.data.opalNotificationUrl;
            });
        }
    };

    //=================================================
    // Get any images that will be needed
    //=================================================
    $scope.opalLogo = "";
    toDataURL("./images/opal_logo.png", dataUrl => {$scope.opalLogo = dataUrl;});

    function toDataURL(url, callback)
    {
        var xhr = new XMLHttpRequest();
        xhr.onload = function() {
          var reader = new FileReader();
          reader.onloadend = function() {
            callback(reader.result);
          }
          reader.readAsDataURL(xhr.response);
        };
        xhr.open('GET', url);
        xhr.responseType = 'blob';
        xhr.send();
    }

    //=========================================================================
    // Set the firebase connection
    //=========================================================================

    //function that sets up a firebase connection
    //the firebase array connected to changes everyday (create a new firebase each day so as to log events)
    //called automatically if the user has a defined clinical area, other wise the user has to choose one
    var firebaseScreenRef = '';
    var connectToFirebase = function()
    {
        var FirebaseUrl = $scope.pageSettings.FirebaseUrl + $scope.pageSettings.ClinicalArea + "/" + today;

        firebaseScreenRef = new Firebase(FirebaseUrl); // firebase connection

        firebaseScreenRef.authWithCustomToken($scope.pageSettings.FirebaseSecret,()=>{});

        $scope.screenRows = $firebaseObject(firebaseScreenRef);

        //once we have the firebase array, we load the rest of the page
        $scope.screenRows.$loaded().then(function()
        {
            //create an array in firebase that contains a list of patients who have to be weighed (if it doesn't already exist)
            //also remove the previous day's array
            if(
                ($scope.screenRows.hasOwnProperty("ToBeWeighed") && $scope.screenRows["ToBeWeighed"].CreatedOn != today) || !$scope.screenRows.hasOwnProperty("ToBeWeighed")
            )
            {
                firebaseScreenRef.child("ToBeWeighed").set({CreatedOn: today});
            }

            if(
                            ($scope.screenRows.hasOwnProperty("zoomLinkSent") && $scope.screenRows["zoomLinkSent"].CreatedOn != today) || !$scope.screenRows.hasOwnProperty("zoomLinkSent")
                    ) firebaseScreenRef.child("zoomLinkSent").set({CreatedOn: today});

            //Prepare a simple object to hold metadata - for now just the timestamp of the most recent call
            if(!$scope.screenRows.hasOwnProperty("Metadata"))
            {
                firebaseScreenRef.child("Metadata").set({LastUpdated: Firebase.ServerValue.TIMESTAMP});
            }

            //create an array in firebase that specifies if the page should reload. By default it shouldn't and the variables shouldn't be changed inside of the VWR
            /*if(!$scope.screenRows.hasOwnProperty("FirebaseSettings"))
            {
                firebaseScreenRef.child("FirebaseSettings").set({NewVersionAvailable: 0, EmergencyRefresh: 0});
            }*/

            //setup a watcher that checks if the page needs to be refreshed
            /*$scope.watch(function()
            {
                if(!$scope.screenRows.$getRecord("FirebaseSettings") {return null;}
                else {return $scope.screenRows.$getRecord("FirebaseSettings");}
            }, function (newValue,oldValue*/

            //firebaseScreenRef.child("21265-251069-2018-09-07 23:57:00").remove();
            //firebaseScreenRef.child("45676-2016542-Aug 24 2018 08:57:00:000PM").remove();
            //firebaseScreenRef.child("827-573938-2018-08-24 14:43:00").remove();

            //get the list of all resources/locations/appointments available from WRM
            //also set the selected resources that we got from the profile
            $scope.allResources = [];
            $scope.selectedResources = $scope.pageSettings.Resources;

            $scope.allLocations = [];
            $scope.selectedLocations = $scope.pageSettings.Locations;

            $scope.allAppointments = [];
            $scope.selectedAppointments = $scope.pageSettings.Appointments;

            $http({
                url: "php/getAllOptions.php",
                method: "GET",
                params:
                {
                    speciality: $scope.pageSettings.Speciality,
                    clinicalArea: $scope.pageSettings.ClinicalArea
                }
            }).then(function (response)
            {
                var options = response.data;

                $scope.allResources = options.Resources;
                $scope.allLocations = options.Locations;
                $scope.allAppointments = options.Appointments;

                $scope.resourceLoadingEnabled = 1;
            });

            //make sure we have the right waiting room
            $scope.pageSettings.WaitingRoom = "BACK TO WAITING ROOM";

            //load checkin data
            $scope.patientLoadingEnabled = 1;
            loadPatients();

            // set the interval for page updates by polling regularly
            if(!$scope.intervalAlreadySet)
            {
                $interval(function()
                {
                    if($scope.patientLoadingEnabled)
                    {
                        loadPatients();
                    }
                },3500);

                $scope.intervalAlreadySet = 1;
            }
        });
    }

    $scope.openLegendDialog = function()
    {
        var legend = $mdDialog.confirm(
        {
            templateUrl: './js/vwr/templates/legendDialog.htm'
        })
        .ariaLabel('Legend Dialog')
        .clickOutsideToClose(true);

        $mdDialog.show(legend);
    }

    $scope.changeClinicalArea = function()
    {
        var answer = $mdDialog.confirm(
            {
                templateUrl: './js/vwr/templates/areaDialog.htm',
                controller: function($scope)
                {
                    $scope.complete = function (option)
                    {
                        $mdDialog.hide(option);
                    }
                }

            })
            .ariaLabel('Area Dialog')
            .clickOutsideToClose(true);

        $mdDialog.show(answer).then(function (result)
        {
            $scope.pageSettings.ClinicalArea = result;
            $scope.patientLoadingEnabled = 0; //temporarly disable loading the checkin file
            $scope.resourceLoadingEnabled = 0; //temporarly disable open the selector modal
            connectToFirebase();
        },function() {});
    }

    if($scope.pageSettings.ClinicalArea) {connectToFirebase();}
    else {$scope.changeClinicalArea();}

    $scope.changeSortOrder = function()
    {
        var answer = $mdDialog.confirm(
            {
                templateUrl: './js/vwr/templates/sortDialog.htm',
                controller: function($scope)
                {
                    $scope.priority = "+";

                    $scope.complete = function (option)
                    {
                        $mdDialog.hide(option);
                    }
                }

            })
            .ariaLabel('Sort Dialog')
            .clickOutsideToClose(true)

        $mdDialog.show(answer).then(function (result)
        {
            $scope.pageSettings.sortOrder = result;
        },function() {});
    }

    //=========================================================================
    // Function to call the patient - message to screen and SMS
    //=========================================================================
    $scope.callPatient = function (patient,destination,sendSMS,updateDB)
    {
        //-----------------------------------------------------------------------
        // First, check that there are no other patients with similar names
        // checked in. If there are, we will need to display extra identifier information
        // to ensure that the correct patient is called
        // Since we need the answwho have a mod planer of how many other patients there are before
        // sending the data to the screens we need to put the update to Firebase
        // inside the $http function
        //-----------------------------------------------------------------------
        //first we check in there are any other patients with the same name as the one we are checking in
        //if there are, we add the patient's date of birth to the screen display
        $http({
            url: "php/getSimilarCheckins.php",
            method: "GET",
            params:
            {
                firstName: patient.FirstName,
                lastNameFirstThree: patient.SSN,
                patientId: patient.PatientId
            }
        }).then(function (response)
        {
            $scope.numNames = response.data;

            var pseudoLastName;

            if($scope.numNames == 0)
            {
                pseudoLastName = patient.SSN + "*****"; // first three digits of last name
            }
            else
            {
                // The Medicare number has 50 added on to the month of birth for women
                // If > 50, then subtract off 50
                if(patient.MONTHOFBIRTH > 50){patient.MONTHOFBIRTH -= 50;}

                if(patient.MONTHOFBIRTH == 1){patient.MONTHOFBIRTH = "Jan";}
                if(patient.MONTHOFBIRTH == 2){patient.MONTHOFBIRTH = "Fev / Feb";}
                if(patient.MONTHOFBIRTH == 3){patient.MONTHOFBIRTH = "Mar";}
                if(patient.MONTHOFBIRTH == 4){patient.MONTHOFBIRTH = "Avr / Apr";}
                if(patient.MONTHOFBIRTH == 5){patient.MONTHOFBIRTH = "Mai / May";}
                if(patient.MONTHOFBIRTH == 6){patient.MONTHOFBIRTH = "Jui / Jun";}
                if(patient.MONTHOFBIRTH == 7){patient.MONTHOFBIRTH = "Jul";}
                if(patient.MONTHOFBIRTH == 8){patient.MONTHOFBIRTH = "Aou / Aug";}
                if(patient.MONTHOFBIRTH == 9){patient.MONTHOFBIRTH = "Sep";}
                if(patient.MONTHOFBIRTH == 10){patient.MONTHOFBIRTH = "Oct";}
                if(patient.MONTHOFBIRTH == 11){patient.MONTHOFBIRTH = "Nov";}
                if(patient.MONTHOFBIRTH == 12){patient.MONTHOFBIRTH = "Dec";}

                pseudoLastName = patient.SSN + " (Naissance/Birthday: " + patient.DAYOFBIRTH + " " + patient.MONTHOFBIRTH + ")";
            }

            //-----------------------------------------------------------------------
            // Message to screens - add this patient's details to our firebase
            // First create a child object for this patient and then fill the data
            //-----------------------------------------------------------------------

            //if the destination is a waiting room, don't put the appointment in firebase
            if(!/WAITING ROOM/.test(destination.LocationId))
            {
                firebaseScreenRef.child(patient.Identifier).set(
                {
                    FirstName: CryptoJS.AES.encrypt(patient.FirstName,'secret key 123').toString(), //encrypt the first name, will be decrypted by the screens later,
                    PseudoLastName: pseudoLastName,
                    PatientId: patient.PatientId,
                    Destination: destination,
                    PatientStatus: 'Called',
                    Appointment: patient.AppointmentName,
                    Resource: patient.ResourceName,
                    ScheduledActivitySer: patient.ScheduledActivitySer,
                    ScheduledActivitySystem: patient.CheckinSystem,
                    Timestamp: Firebase.ServerValue.TIMESTAMP
                });

                $scope.logMessage("call_FB","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.ScheduledActivitySer + patient.CheckinSystem +" inserted in firebase "+ $scope.pageSettings.ClinicalArea +" at destination "+ destination.ScreenDisplayName +" with status 'Called'");

                // Update the timestamp in the firebase array
                firebaseScreenRef.child("Metadata").update({LastUpdated: Firebase.ServerValue.TIMESTAMP});
            }

            if(sendSMS)
            {
                //-----------------------------------------------------------------------
                // Send the patient an SMS message
                //-----------------------------------------------------------------------
                $http({
                    url: "php/sendSMSRoom",
                    method: "GET",
                    params:
                    {
                        patientId: patient.PatientId,
                        room_FR: destination.VenueFR,
                        room_EN: destination.VenueEN
                    }
                });

                //-----------------------------------------------------------------------
                // Attempt to send a push notificiation to the patient's Opal-app enabled
                // smartphone. This is an "attempt" as we don't know if the patient has the
                // Opal app or not. The php script will take care of whether the patient
                // has a phone and Opal or not...
                //-----------------------------------------------------------------------
                //for now, only RVH patients have opal
                let correctSerNum = patient.ScheduledActivitySer;
                if(patient.CheckinSystem === "Aria") {
                    correctSerNum = patient.AppointmentId.replace(/MEDIAria/,"");
                }

                $http({
                    url: $scope.opalNotificationUrl,
                    method: "GET",
                    params:
                    {
                        patientid: patient.Mrn,
                        appointment_ariaser: correctSerNum,
                        room_FR: destination.VenueFR,
                        room_EN: destination.VenueEN
                    }
                });
            }
        });

        //-----------------------------------------------------------------------
        // Check the patient into the calling location - all subsequent appointments
        // will see this location
        //-----------------------------------------------------------------------
        if(updateDB)
        {
            $http({
                url: "php/checkinPatientAriaMedi.php",
                method: "GET",
                params:
                {
                    checkinVenue: destination.LocationId,
                    appointmentSer: patient.Identifier,
                    patientId: patient.PatientId
                }
            }).then(function()
            {
                $scope.logMessage("call_DB","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.ScheduledActivitySer + patient.CheckinSystem +" inserted in db at location "+ destination.LocationId);
            });
        }
    } // end callPatient() function

    //calls the patient (displays their name on the screen) again
    $scope.callPatientAgain = function (patient,sendSMS)
    {
        //its possible the page has been refreshed and that we no longer remember where the patient was called to in the first place
        //this is a problem if we want to call the patient again so check if the firebase object has the patients current location (and use it)
        //if we are calling the patient again, we should have the Destination property in firebase
        //of course, its possible that someone spams the call button right after calling the patient the first time but it shouldn't be an issue since the first call is still in effect

        //retext and update timestamp; no need to re-put the patient in the same room in the DB
        var destination = $scope.screenRows[patient.Identifier].Destination;

        $scope.callPatient(patient,destination,sendSMS,false);

        $scope.logMessage("call_again","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.ScheduledActivitySer + patient.CheckinSystem +" was called again at location "+ destination);
    }

    //=========================================================================
    // Function to remove a patient from Firebase
    //========================================================================
    $scope.removeFromFB = function (patient)
    {
        // Remove the patient from Firebase - will return to the "Call Patient" button
        firebaseScreenRef.child(patient.Identifier).remove();

        $scope.logMessage("remove_FB","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.ScheduledActivitySer + patient.CheckinSystem +" removed from firebase "+ $scope.pageSettings.ClinicalArea);

        // Update the metadata timestamp
        firebaseScreenRef.child("Metadata").update({ LastUpdated: Firebase.ServerValue.TIMESTAMP});
    }

    //=========================================================================
    // Function to send a patient to a specifed area
    // Used for 'SENT FOR X-RAY', 'SEND FOR PHYSIO', and also to send patients back to the waiting room
    //=========================================================================
    $scope.sendToLocation = function (patient,sendLocation,removeFromFB)
    {
        //-----------------------------------------------------------------------
        // Check the patient into the SENT FOR X-RAY location
        //-----------------------------------------------------------------------
        $http({
            url: "php/checkinPatientAriaMedi.php",
            method: "GET",
            params:
            {
                checkinVenue: sendLocation,
                appointmentSer: patient.Identifier,
                patientId: patient.PatientId,
            }
        }).then(function()
        {
            $scope.logMessage("send_pat","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.ScheduledActivitySer + patient.CheckinSystem +" inserted in db at location "+ sendLocation);

            if(removeFromFB) {$scope.removeFromFB(patient);}
        });
    }

    //=========================================================================
    // Function to discharge a patient - this option should only be available for
    // Medivisit patients
    // Check the patient out of the present appoint (change the status). Then,
    // search for future open appointments today for the patient and check him/her
    // in to any that exist
    //=========================================================================
    $scope.completeAppointment = function(patient)
    {
        // Check the patient in for his/her remaining appointments but for a venue
        // that indicates that the current appointment is complete
        $http({
            url: "php/completeAppointment.php",
            method: "GET",
            params:
            {
                checkoutVenue: "BACK TO WAITING ROOM",
                scheduledActivitySer: patient.ScheduledActivitySer
            }
        }).then(function (response)
        {
            // Mark patient as CheckedOut on Firebase
            firebaseScreenRef.child(patient.Identifier).update(
            {
                PatientStatus: "CheckedOut"
            });

            $scope.logMessage("compl_mv","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.ScheduledActivitySer + patient.CheckinSystem +" inserted in db at location BACK TO WAITING ROOM and patient status on firebase "+ $scope.pageSettings.ClinicalArea +" changed to CheckedOut");

            // Update the metadata timestamp
            firebaseScreenRef.child("Metadata").update({ LastUpdated: Firebase.ServerValue.TIMESTAMP});
        });
    }

    //function that checks an appointment name and determines whether the patient has to be weighed
    $scope.screenRows.$loaded().then(function()
    {
        $scope.patientWeightRequired = function (patient)
        {
            if($scope.patientLoadingEnabled)
            {
                if(patient.Weight != null && patient.Weight >= 0)
                {
                    return "Already Taken";
                }
                else if(/(CON-GP|FU-AD-CHM-GP|FU-ORL-TX-GP|FU-SD-CHM-GP|FUSD-CHM-GP|HCONSULT|HFU-AD-CHM|HFU-ORL-TX|HFU-SD-CHM|CONSULT NEW OUT LFTU|FOLLOW UP OUT LTFU|H|HW|W)/.test(patient.AppointmentName))
                {
                    return "Yes";
                }
                else if(patient.ResourceName === "Lung Clinic")
                {
                    return "Yes";
                }
                else if($scope.screenRows['ToBeWeighed'].hasOwnProperty(patient.PatientId + patient.CheckinSystem))
                {
                    return "Yes";
                }
                else {return "No";}
            }
        }
    });

    //=========================================================================
    // Function to add the patient to a special firebase array that monitors
    //    patients that have to be weighed
    //=========================================================================
    $scope.addToWeighArray = function (patient)
    {
        //Add the patient to ToBeWeighed firebase array
        //for older browsers, create the object to pass first
        var tempObj = {};
        tempObj[patient.PatientId + patient.CheckinSystem] = 1;

        firebaseScreenRef.child("ToBeWeighed").update(tempObj);

        $scope.logMessage("add_weight_arr","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.ScheduledActivitySer + patient.CheckinSystem +" added to weight array in firebase "+ $scope.pageSettings.ClinicalArea);

        // Update the metadata timestamp
        //firebaseScreenRef.child("Metadata").update({LastUpdated: Firebase.ServerValue.TIMESTAMP});
    }

    //define a useful function that will be used to check in patients to rooms later
    $scope.callPatientToSelectedLocation = function (patient,selectedLocation,sendSMS)
    {
        //====================================================================
        // Call the patient to the selected location
        //====================================================================
        $http({
            url: "php/getLocationInfo.php",
            method: "GET",
            params: {Location: selectedLocation}
        }).then(function (response)
        {
            var patientDestination = response.data;
            patientDestination.LocationId = selectedLocation;

            //check if the patient is already checked in where they are
            //if they are, then just update the firebase metadata

            var currentDestination = null;
            if($scope.screenRows.hasOwnProperty(patient.Identifier)) {currentDestination = $scope.screenRows[patient.Identifier].Destination;}

            if(angular.equals(currentDestination,patientDestination))
            {
                $scope.callPatientAgain(patient,sendSMS);
            }
            else {$scope.callPatient(patient,patientDestination,sendSMS,true);}
        });
    }

    $scope.checkIfPatientInExamRoom = function (patient)
    {
        for(var i = 0; i < $scope.allLocations.length; i++)
        {
            if($scope.allLocations[i].Name == patient.VenueId && $scope.allLocations[i].Type == 'ExamRoom')
            {
                return true;
            }
        }

        return false;
    }

    //=========================================================================
    // Open the call room modal
    //=========================================================================
    $scope.openCallModal = function (patient,callCondition)
    {
        //first check which selected locations we are considering
        var selectedLocations = $scope.selectedLocations;
        var alertMessage = "Please select a Call Location."

        if(callCondition == "ASSIGN EXAM ROOM")
        {
            selectedLocations = $filter('filter')($scope.selectedLocations,{Type: "ExamRoom"});
            alertMessage = "Please select an Exam Room";
        }

        //if we current only have on location selected, simply check the patient into that location without opening the modal
        //intermediate and treatment venues can hold infinite people so we don't need to check if anyone else is checked into the room
        //exam rooms however can only hold one person, so if there are other patients checked into it, we put the other patients into the 'BACK TO WAITING ROOM' room

        //look at the types of selected location to see what type of situation we are dealing with
        var situation =
        {
            iPresent: 0, //intermediate venue
            tPresent: 0, //treatment venue
            ePresent: 0 //exam room
        };

        angular.forEach(selectedLocations,function (opt)
        {
            if(opt.Type == 'IntermediateVenue') {situation.iPresent = 1;}
            else if(opt.Type == 'TreatmentVenue') {situation.tPresent = 1;}
            else if(opt.Type == 'ExamRoom') {situation.ePresent = 1;}
        });

        if(situation.ePresent && (situation.iPresent || situation.tPresent)) {situation = "MIXED";}
        else if(situation.iPresent || situation.tPresent) {situation = "VENUE ONLY";}
        else if(situation.ePresent) {situation = "EXAM ONLY";}

        if(selectedLocations.length == 0)
        {
            //if there are no selectedLocations, tell the user to select some
            $mdDialog.show($mdDialog.alert()
                .clickOutsideToClose(true)
                .title('No Call Locations Selected')
                .textContent(alertMessage)
                .ariaLabel('Call Location Dialog')
                .ok('Ok')
            );
        }
        else if(selectedLocations.length == 1)
        {
            //if we only have one location selected, we can automatically call the patient to that location without opening the modal
            //however, if the selected location is an exam room, we have to check if its occupied
            //if it is, we sent the occupying patient to the 'BACK TO WAITING ROOM' room

            if(situation == "VENUE ONLY")
            {
                $scope.logMessage("call_venue_only","General","Function call on Patient "+ patient.PatientId +" with appointment serial "+ patient.ScheduledActivitySer + patient.CheckinSystem +" and location "+ selectedLocations[0].Name);

                $scope.callPatientToSelectedLocation(patient,selectedLocations[0].Name,true);
            }
            else if(situation == "EXAM ONLY")
            {
                $http({
                    url: "php/getOccupants.php",
                    method: "GET",
                    params: {checkinVenue: selectedLocations[0].Name}
                }).then(function (response)
                {
                    var occupyingPatient = response.data[0];

                    if(
                        (occupyingPatient.PatientIdRVH != "Nobody" && occupyingPatient.PatientIdRVH != patient.PatientIdRVH)
                        || (occupyingPatient.PatientIdMGH != "Nobody" && occupyingPatient.PatientIdMGH != patient.PatientIdMGH)
                    )
                    {
                        $scope.logMessage("force_remove_exam_only","General","Function call on Patient "+ occupyingPatient.PatientId +" and location BACK TO WAITING ROOM");

                        $scope.sendToLocation(occupyingPatient,"BACK TO WAITING ROOM",false);

                        //for each appointment the occupying patient has, find it in the checkin list and remove it from FB
                        angular.forEach($scope.checkins,function (matchingPatient)
                        {
                            if(matchingPatient.PatientIdRVH == occupyingPatient.PatientIdRVH && matchingPatient.PatientIdMGH == occupyingPatient.PatientIdMGH)
                            {
                                $scope.logMessage("force_remove_FB_exam_only","General","Function call on Patient "+ matchingPatient.PatientId +" with appointment serial "+ matchingPatient.ScheduledActivitySer + matchingPatient.CheckinSystem);

                                $scope.removeFromFB(matchingPatient);
                            }
                        });
                    }

                    if(callCondition == "ASSIGN EXAM ROOM")
                    {
                        $scope.sendToLocation(patient,selectedLocations[0].Name,false);
                    }
                    else {$scope.callPatientToSelectedLocation(patient,selectedLocations[0].Name,true);}
                });

            }
        }
        else
        {
            var modalInstance = $uibModal.open({
                animation: true,
                templateUrl: 'js/vwr/templates/callModal.htm',
                controller: callModalController,
                scope: $scope,
                size: 'sm',
                resolve:
                {
                    selectedLocations: function ()
                    {
                        return selectedLocations;
                    }
                }
            }).result.then(function (result)
            {
                var selectedLocation = result.selectedLocation;
                var occupyingIds = result.occupyingIds;

                //loop through the list of patients that were chosen to be checkout out
                angular.forEach(occupyingIds,function (id)
                {
                    if(id.PatientIdRVH != patient.PatientIdRVH && id.PatientIdMGH != patient.PatientIdMGH)
                    {
                        $scope.logMessage("force_remove","General","Function call on Patient "+ id.PatientId +" and location BACK TO WAITING ROOM");

                        $scope.sendToLocation({PatientIdRVH: id.PatientIdRVH,PatientIdMGH: id.PatientIdMGH},"BACK TO WAITING ROOM",false);

                        //for each appointment the occupying patient has, find it in the checkin list and remove it from FB
                        angular.forEach($scope.checkins,function (matchingPatient)
                        {
                            if(matchingPatient.PatientIdRVH == id.PatientIdRVH && matchingPatient.PatientIdMGH == id.PatientIdMGH)
                            {
                                $scope.logMessage("force_remove_FB","General","Function call on Patient "+ matchingPatient.PatientId +" with appointment serial "+ matchingPatient.ScheduledActivitySer + matchingPatient.CheckinSystem);

                                $scope.removeFromFB(matchingPatient);
                            }
                        });
                    }
                });

                if(callCondition == "ASSIGN EXAM ROOM")
                {
                    $scope.sendToLocation(patient,selectedLocation,false);
                }
                else {$scope.callPatientToSelectedLocation(patient,selectedLocation,true);}
            });
        }
    };

    //initialize the modal function that lets us select resources/locations/appointments
    $scope.openSelectorModal = function (options,selectedOptions,title)
    {
        var modalInstance = $uibModal.open(
        {
            animation: true,
            templateUrl: 'js/vwr/templates/selectorModal.htm',
            controller: selectorModalController,
            windowClass: 'selectorModal',
            resolve:
            {
                inputs: function() {return {'options': options, 'selectedOptions': selectedOptions, 'title': title};}
            }
        }).result.then(function(selected)
        {
            selectedOptions.length = 0; //clear array
            angular.forEach(selected,function (op)
            {
                selectedOptions.push(op);
            });
        });
    }

    //=========================================================================
    // Open the questionnaire modal
    //=========================================================================
    $scope.openQuestionnaireModal = function (patient)
    {
        var modalInstance = $uibModal.open(
        {
            animation: true,
            templateUrl: 'js/vwr/templates/questionnaireModal.htm',
            controller: questionnaireModalController,
            windowClass: 'questionnaireModal',
            size: 'lg',
            //backdrop: 'static',
            resolve:
            {
                patient: function() {return patient;}
            }
        }).result.then(function(response)
        {

        });
    }


    //=========================================================================
    // Open the SMS modal
    //=========================================================================
    $scope.openSMSRegistrationModal = function (patient)
    {
        var modalInstance = $uibModal.open(
        {
            animation: true,
            templateUrl: 'js/vwr/templates/registerSMSModal.htm',
            controller: registerSMSModalController,
            windowClass: 'registerSMSModal',
            size: 'lg',
            //backdrop: 'static',
            resolve:
            {
                patient: function() {return patient;}
            }
        }).result.then(function(response)
        {

        });
    }


    //=========================================================================
    // Open the weigh patient modal
    //=========================================================================
    $scope.openWeightModal = function (patient)
    {
        var modalInstance = $uibModal.open(
        {
            animation: true,
            templateUrl: 'js/vwr/templates/weightModal.htm',
            controller: weightModalController,
            windowClass: 'weightModal',
            size: 'lg',
            backdrop: 'static',
            resolve:
            {
                patient: function() {return patient;}
            }
        }).result.then(function (response)
        {
            //if the weight was updated, remove the patient from the ToBeWeighed FB array
            if(response)
            {
                //create object for older browsers ({[varName]: 1} syntax doesn't work)
                var tempObj = {};
                tempObj[patient.PatientId + patient.CheckinSystem] = {};

                firebaseScreenRef.child("ToBeWeighed").update(tempObj);

                $scope.logMessage("remove_weight_arr","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.ScheduledActivitySer + patient.CheckinSystem +" removed from weight array in firebase "+ $scope.pageSettings.ClinicalArea);
            }
        });
    }

    //=========================================================================
    // Open the form modal
    //=========================================================================
    $scope.openFormModal = function (patient)
    {
        /*
        var modalInstance = $uibModal.open(
        {
            animation: true,
            templateUrl: 'js/vwr/templates/formModal.htm',
            controller: formModalController,
            windowClass: 'formModal',
            resolve:
            {
                patient: function() {return patient;}
            }
        }).result.then(function (result)
        {
            //complete the patient's appointment when the modal is closed
            $scope.completeAppointment(patient);
        });
        */
        $scope.completeAppointment(patient);
    }

    $scope.logMessage = function (identifier,type,message)
    {
        $http({
            url: "perl/logMessage.pl",
            method: "GET",
            params:
            {
                printJSON: 1,
                filename: 'VirtualWaitingRoom.html',
                identifier: identifier,
                type: type,
                message: message
            }
        });
    }

    //-------------------------------------
    // zoom button functionality
    //-------------------------------------
    $scope.pageSettings.zoomLink = "";

    $scope.focusOnId = function(id)
    {
        document.getElementById(id).focus();
    }

    $scope.sendZoomLink = function(patient)
    {
        $http({
            url: "php/sendSmsForZoom",
            method: "GET",
            params:
            {
                patientId: patient.PatientId,
                zoomLink:   $scope.pageSettings.zoomLink,
                resName:    patient.ResourceName
            }
        })
        .then( _ => {
            firebaseScreenRef.child("zoomLinkSent").update({[patient.Identifier]: 1});
        });
    }

    $scope.isZoomAppointment = function(appointmentName)
    {
        return /TV-NP|TV-NP-FAM|TV-FU|TV-FU-FAM|TV-ADCHM-FU|TV-SDCHM-FU|TV-ORAL-FU|TCC\/TCR|PHONE|PHONE-SDCHM|FOLLOW UP TELEMED MORE\/30 DAYS|FOLLOW UP TELEMED LESS\/30 DAYS|CONSULT RETURN TELEMED|CONSULT NEW TELEMED|INTRA TREAT TELEMED|E-IMPAQc CONSULT NEW TELEMED|E-IMPAQc FOLLOW UP TELEMED LESS\/30 DAYS|E-IMPAQc FOLLOW UP TELEMED MORE\/30 DAYS|E-IMPAQc INTRA TREAT TELEMED|CONSULT NEW ZOOM|CONSULT RETURN ZOOM|FOLLOW UP ZOOM LESS\/30 DAYS|FOLLOW UP ZOOM MORE\/30 DAYS/.test(appointmentName);
    }

    $scope.openZoomLink = function()
    {
        $window.open($scope.pageSettings.zoomLink,"_blank");
    }

    $scope.wasZoomLinkSent = function(patient)
    {
        return $scope.screenRows["zoomLinkSent"].hasOwnProperty(patient.Identifier);
    }

    //misc functions

    $scope.pageSettings.SelectedRowTypes = {
        CheckedIn: true,
        NotCheckedIn: false,
        Completed: false
    };

    $scope.sortByRowType = function(patient)
    {
        if(patient.RowType === "CheckedIn") return 0;
        else if(patient.RowType === "NotCheckedIn") return 1;
        else if(patient.RowType === "Completed") return 2;

        return 3;
    }

    $scope.determineRowClass = function(patient)
    {
        let cssClass = [];
        if(patient.TimeRemaining < 5) cssClass.push("row-checkedIn");

        if(patient.RowType === "NotCheckedIn") cssClass.push("row-notCheckedIn");
        else if(patient.RowType === "Completed") cssClass.push("row-completed");

        return cssClass;
    }

});
