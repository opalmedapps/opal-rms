//virtualWaitingRoom Controller

myApp.controller("virtualWaitingRoomController",function ($scope,$uibModal,$http,$firebaseObject,$interval,$filter,$mdDialog,$window,ProfileSettings,WearableCharts)
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

    today = $filter('date')(today,'MM-dd-yyyy');

    // get the time right now and do it on a regular basis so that the
    // right now time is updated continuously
    $interval(function()
    {
        var dateTimeNow = new Date();
        $scope.timeNow = dateTimeNow.getTime();

        //check if the autofetched resources have expired, that is the time went from AM->PM or PM->AM
        var dateNow = dateTimeNow.getDate();

        //alternatively, if it is 12:01AM then the page has expired and needs to connect to the new day's firebase
        if(dateToday != dateNow) {
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


    // Function to grab the list of patients
    var loadPatients = function ()
    {
        $http.get($scope.pageSettings.CheckInFile)
        .then(async function(response){
            /* Add unreadWearablesData counts to the received checkins records by making a call to the Django backend.
            Since the cronjob script (e.g., /php/cron/generateVwrAppointments.php) does not have the required
            sessionid cookie to fetch the unviewed health data counts, we populate those values here
            (e.g., a call from the browser). */
            let checkins = response?.data;

            // Check if checkins does not exist, is not an array, or is empty
            if (!Array.isArray(checkins) || !checkins.length) return;

            // Find backend host's address from the wearables URL.
            const wearableDataChartsURL = new URL(checkins[0]?.wearablesURL);
            const backendHost = wearableDataChartsURL.origin;
            const patientUUIDsList = checkins.map(
                ({OpalUUID}) => ({'patient_uuid': OpalUUID}),
            );
            const unreadWearablesCounts = await WearableCharts.getUnreadWearablesDataCounts(
                backendHost + '/api/patients/health-data/unviewed/',
                patientUUIDsList,
            );

            // Set unread wearables data counts to the $scope.checkins
            checkins.map(
                // Iterate through every checkin object and add unreadWearablesData field
                (checkin) => {
                    let checkinPatient = unreadWearablesCounts.find(
                        patient => patient?.patient_uuid == checkin?.OpalUUID
                    );
                    // Set unreadWearablesData to 0 if the backend does not return a count for this checkin/patient
                    checkin.unreadWearablesData = checkinPatient?.count ?? 0;
                }
            );

            // Update scope variable
            $scope.checkins = checkins;
        });
    };

    //=================================================
    // Get any images that will be needed
    //=================================================
    $scope.opalLogo = "";

    let xhr = new XMLHttpRequest();
    xhr.onload = function()
    {
        let reader = new FileReader();
        reader.onloadend = _ => {$scope.opalLogo = reader.result;}
        reader.readAsDataURL(xhr.response);
    };
    xhr.open("GET","VirtualWaitingRoom/images/opal_logo.png");
    xhr.responseType = "blob";
    xhr.send();

    //=========================================================================
    // Set the firebase connection
    //=========================================================================

    //function that sets up a firebase connection
    //the firebase array connected to changes everyday (create a new firebase each day so as to log events)
    //called automatically if the user has a defined clinical area, other wise the user has to choose one
    var firebaseScreenRef = '';
    var connectToFirebase = function()
    {
        var FirebaseUrl = $scope.pageSettings.FirebaseUrl + $scope.pageSettings.ClinicHubId + "/" + today;

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
                ($scope.screenRows.hasOwnProperty("zoomLinkSent") && $scope.screenRows["zoomLinkSent"].CreatedOn != today)
                || !$scope.screenRows.hasOwnProperty("zoomLinkSent")
            ) {
                firebaseScreenRef.child("zoomLinkSent").set({CreatedOn: today});
            }

            //Prepare a simple object to hold metadata - for now just the timestamp of the most recent call
            if(!$scope.screenRows.hasOwnProperty("Metadata"))
            {
                firebaseScreenRef.child("Metadata").set({LastUpdated: Firebase.ServerValue.TIMESTAMP});
            }

            //Prepare an object to hold patient objects
            if(!$scope.screenRows.hasOwnProperty("patients"))
            {
                firebaseScreenRef.child("patients").set({LastUpdated: Firebase.ServerValue.TIMESTAMP});
            }

            //get the list of all resources/locations available from WRM
            //also set the selected resources that we got from the profile
            $scope.allResources = [];
            $scope.selectedResources = $scope.pageSettings.Resources;

            $scope.allLocations = [];
            $scope.selectedLocations = $scope.pageSettings.Locations;

            $http({
                url: "php/api/private/v1/appointment/getClinics",
                method: "GET",
                params: {
                    speciality: $scope.pageSettings.Speciality,
                    clinicHub: $scope.pageSettings.ClinicHubId
                }
            }).then(function(response)
            {
                $scope.allResources = response.data.data.map(x => ({Name: x.description,Type: "Resource"}));

                $http({
                    url: "php/api/private/v1/hospital/getRooms",
                    method: "GET",
                    params: {
                        clinicHub: $scope.pageSettings.ClinicHubId
                    }
                }).then(function(response) {
                    $scope.allLocations = response.data.data;

                    //since the room object coming from the profile is incomplete, fill out the object by finding it in the list of rooms
                    $scope.selectedLocations = $scope.selectedLocations.map(x => $scope.allLocations.find(y => x.Name === y.Name));
                    $scope.selectedLocations = $scope.selectedLocations.filter(x => x); //filter out undefined

                    $scope.resourceLoadingEnabled = 1;
                });
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
                    if($scope.patientLoadingEnabled) {
                        loadPatients();
                    }
                },3500);

                $scope.intervalAlreadySet = 1;
            }
        });
    }

    connectToFirebase();

    $scope.openLegendDialog = function()
    {
        var legend = $mdDialog.confirm(
        {
            templateUrl: 'VirtualWaitingRoom/js/vwr/templates/legendDialog.htm'
        })
        .ariaLabel('Legend Dialog')
        .clickOutsideToClose(true);

        $mdDialog.show(legend);
    }

    $scope.changeSortOrder = function()
    {
        var answer = $mdDialog.confirm(
            {
                templateUrl: 'VirtualWaitingRoom/js/vwr/templates/sortDialog.htm',
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
    $scope.callPatient = function(patient,destination,sendSMS,updateDB)
    {
        //first we check in there are any other patients with a similar name as the one we are calling
        //if there are, we add the patient's date of birth to the screen display
        let similarPatients = $scope.checkins.filter(x =>
            x.RowType === "CheckedIn"
            && x.FirstName === patient.FirstName
            && new RegExp("^"+x.LastName.substring(0,3)).test(patient.LastName)
            && x.PatientId !== patient.PatientId
        );

        let pseudoLastName = patient.LastName.substring(0,3) + "*****"; // first three characters of last name;

        if(similarPatients.length !== 0) {
            pseudoLastName = patient.LastName.substring(0,3) + "***** (Naissance/Birthday: " + patient.Birthday + ")";
        }

        //-----------------------------------------------------------------------
        // Message to screens - add this patient's details to our firebase
        // First create a child object for this patient and then fill the data
        //-----------------------------------------------------------------------

        //if the destination is in a waiting room, don't put the appointment in firebase
        if(!/WAITING ROOM/i.test(destination.Name))
        {
            firebaseScreenRef.child("patients").child(patient.AppointmentId).set(
            {
                FirstName: CryptoJS.AES.encrypt(patient.FirstName,'secret key 123').toString(), //encrypt the first name, will be decrypted by the screens later,
                PseudoLastName: pseudoLastName,
                PatientId: patient.PatientId,
                Destination: JSON.parse(angular.toJson(destination)), //remove the $$hashkey property
                PatientStatus: "Called",
                Appointment: patient.AppointmentName,
                Resource: patient.ResourceName,
                AppointmentId: patient.AppointmentId,
                ScheduledActivitySystem: patient.CheckinSystem,
                Timestamp: Firebase.ServerValue.TIMESTAMP
            });

            $scope.logMessage("call_FB","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.AppointmentId + patient.CheckinSystem +" inserted in firebase "+ $scope.pageSettings.ClinicHubId +" at destination "+ destination.ScreenDisplayName +" with status 'Called'");

            // Update the timestamp in the firebase array
            firebaseScreenRef.child("Metadata").update({LastUpdated: Firebase.ServerValue.TIMESTAMP});
        }

        if(sendSMS) {
            //-----------------------------------------------------------------------
            // Send the patient an SMS message
            //-----------------------------------------------------------------------
            $http({
                url: "php/api/private/v1/patient/sms/sendSmsRoom",
                method: "POST",
                data:
                {
                    patientId: patient.PatientId,
                    sourceId: patient.SourceId,
                    sourceSystem: patient.CheckinSystem,
                    roomFr: destination.VenueFR,
                    roomEn: destination.VenueEN
                }
            });
        }

        //-----------------------------------------------------------------------
        // Check the patient into the calling location - all subsequent appointments will see this location
        //-----------------------------------------------------------------------
        if(updateDB) {
            $scope.sendToLocation(patient,destination.Name,false)
            $scope.logMessage("call_DB","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.AppointmentId + patient.CheckinSystem +" inserted in db at location "+ destination.Name);
        }
    }

    //calls the patient (displays their name on the screen) again
    $scope.callPatientAgain = function (patient,sendSMS)
    {
        //its possible the page has been refreshed and that we no longer remember where the patient was called to in the first place
        //this is a problem if we want to call the patient again so check if the firebase object has the patients current location (and use it)
        //if we are calling the patient again, we should have the Destination property in firebase
        //of course, its possible that someone spams the call button right after calling the patient the first time but it shouldn't be an issue since the first call is still in effect

        //retext and update timestamp; no need to re-put the patient in the same room in the DB
        var destination = $scope.screenRows.patients[patient.AppointmentId].Destination;

        $scope.callPatient(patient,destination,sendSMS,false);

        $scope.logMessage("call_again","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.AppointmentId + patient.CheckinSystem +" was called again at location "+ destination);
    }

    //=========================================================================
    // Function to remove a patient from Firebase
    //========================================================================
    $scope.removeFromFB = function (patient)
    {
        // Remove the patient from Firebase - will return to the "Call Patient" button
        firebaseScreenRef.child("patients").child(patient.AppointmentId).remove();

        $scope.logMessage("remove_FB","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.AppointmentId + patient.CheckinSystem +" removed from firebase "+ $scope.pageSettings.ClinicHubId);

        // Update the metadata timestamp
        firebaseScreenRef.child("Metadata").update({ LastUpdated: Firebase.ServerValue.TIMESTAMP});
    }

    //=========================================================================
    // Function to send a patient to a specifed area
    //=========================================================================
    $scope.sendToLocation = function (patient,sendLocation,removeFromFB)
    {
        $http({
            url: "php/api/private/v1/patient/checkInToLocation",
            method: "POST",
            data:
            {
                appointmentId: patient.AppointmentId,
                patientId: patient.PatientId,
                room: sendLocation
            }
        }).then(function()
        {
            $scope.logMessage("send_pat","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.AppointmentId + patient.CheckinSystem +" inserted in db at location "+ sendLocation);

            if(removeFromFB) {$scope.removeFromFB(patient);}
        });
    }

    //=========================================================================
    // Function to discharge a patient
    // Check the patient out of the present appoint (change the status). Then,
    // search for future open appointments today for the patient and check him/her
    // in to any that exist
    //=========================================================================
    $scope.completeAppointment = function(patient)
    {
        // Check the patient in for his/her remaining appointments but for a venue
        // that indicates that the current appointment is complete
        $http({
            url: "php/api/private/v1/appointment/completeAppointment",
            method: "POST",
            data:
            {
                room: "BACK TO WAITING ROOM",
                patientId: patient.PatientId,
                appointmentId: patient.AppointmentId
            }
        }).then(function (response)
        {
            // Mark patient as CheckedOut on Firebase
            firebaseScreenRef.child("patients").child(patient.AppointmentId).update(
            {
                PatientStatus: "CheckedOut"
            });

            $scope.logMessage("compl_mv","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.AppointmentId + patient.CheckinSystem +" inserted in db at location BACK TO WAITING ROOM and patient status on firebase "+ $scope.pageSettings.ClinicHubId +" changed to CheckedOut");

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
                else if(/(CON-GP|FU-AD-CHM-GP|FU-PRIVATE-TX|FU-ORL-TX-GP|FU-SD-CHM-GP|FUSD-CHM-GP|HCONSULT|HFU-AD-CHM|HFU-ORL-TX|HFU-PRIVATE-TX|HFU-SD-CHM|CONSULT NEW OUT LFTU|FOLLOW UP OUT LTFU|H|HW|W)/.test(patient.AppointmentName))
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

        $scope.logMessage("add_weight_arr","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.AppointmentId + patient.CheckinSystem +" added to weight array in firebase "+ $scope.pageSettings.ClinicHubId);
    }

    //call a patient to the selected location
    $scope.callPatientToSelectedLocation = function(patient,selectedLocation,sendSMS)
    {
        //check if the patient is already checked in where they are
        //if they are, then just update the firebase metadata
        let currentDestination = null;

        if($scope.screenRows.patients.hasOwnProperty(patient.AppointmentId)) {
            currentDestination = $scope.screenRows.patients[patient.AppointmentId].Destination;
        }

        if(currentDestination !== null && currentDestination.Name === selectedLocation.Name) {
            $scope.callPatientAgain(patient,sendSMS);
        }
        else {
            $scope.callPatient(patient,selectedLocation,sendSMS,true);
        }
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
        let selectedLocations = $scope.selectedLocations;
        let alertMessage = "Please select a Call Location."

        if(callCondition == "ASSIGN EXAM ROOM")
        {
            selectedLocations = $filter('filter')(selectedLocations,{Type: "ExamRoom"});
            alertMessage = "Please select an Exam Room";
        }

        //if we current only have on location selected, simply check the patient into that location without opening the modal
        //intermediate and treatment venues can hold infinite people so we don't need to check if anyone else is checked into the room
        //exam rooms however can only hold one person, so if there are other patients checked into it, we put the other patients into the 'BACK TO WAITING ROOM' room

        //look at the types of selected location to see what type of situation we are dealing with
        let situation =
        {
            iPresent: 0, //intermediate venue
            ePresent: 0 //exam room
        };

        angular.forEach(selectedLocations,function (opt)
        {
            if(opt.Type == 'IntermediateVenue') {situation.iPresent = 1;}
            else if(opt.Type == 'ExamRoom') {situation.ePresent = 1;}
        });

        if(situation.ePresent && situation.iPresent) {situation = "MIXED";}
        else if(situation.iPresent) {situation = "VENUE ONLY";}
        else if(situation.ePresent) {situation = "EXAM ONLY";}

        if(selectedLocations.length === 0)
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

            if(situation === "VENUE ONLY")
            {
                $scope.logMessage("call_venue_only","General","Function call on Patient "+ patient.PatientId +" with appointment serial "+ patient.AppointmentId + patient.CheckinSystem +" and location "+ selectedLocations[0].Name);

                $scope.callPatientToSelectedLocation(patient,selectedLocations[0],true);
            }
            else if(situation === "EXAM ONLY")
            {
                $http({
                    url: "php/api/private/v1/hospital/getOccupants",
                    method: "GET",
                    params: {"examRooms[]": [selectedLocations[0].Name]}
                }).then(function (response)
                {
                    let occupyingPatient = response.data.data[0];

                    if(
                        occupyingPatient.PatientId != "Nobody"
                        && occupyingPatient.PatientId != patient.PatientId
                    )
                    {
                        $scope.logMessage("force_remove_exam_only","General","Function call on Patient "+ occupyingPatient.PatientId +" and location BACK TO WAITING ROOM");

                        $scope.sendToLocation(occupyingPatient,"BACK TO WAITING ROOM",false);

                        //for each appointment the occupying patient has, find it in the checkin list and remove it from FB
                        angular.forEach($scope.checkins,function (matchingPatient)
                        {
                            if(matchingPatient.PatientId == occupyingPatient.PatientId)
                            {
                                $scope.logMessage("force_remove_FB_exam_only","General","Function call on Patient "+ matchingPatient.PatientId +" with appointment serial "+ matchingPatient.AppointmentId + matchingPatient.CheckinSystem);

                                $scope.removeFromFB(matchingPatient);
                            }
                        });
                    }

                    if(callCondition == "ASSIGN EXAM ROOM") {
                        $scope.sendToLocation(patient,selectedLocations[0].Name,false);
                    }
                    else {
                        $scope.callPatientToSelectedLocation(patient,selectedLocations[0],true);
                    }
                });

            }
        }
        else
        {
            $uibModal.open({
                animation: true,
                templateUrl: 'VirtualWaitingRoom/js/vwr/templates/callModal.htm',
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
            }).result.then(function(result)
            {
                let selectedLocation = result.selectedLocation;
                let occupyingIds = result.occupyingIds;

                //loop through the list of patients that were chosen to be checkout out
                angular.forEach(occupyingIds,function (id)
                {
                    if(id.PatientId !== patient.PatientId)
                    {
                        $scope.logMessage("force_remove","General","Function call on Patient "+ id.PatientId +" and location BACK TO WAITING ROOM");

                        $scope.sendToLocation({PatientId: id.PatientId},"BACK TO WAITING ROOM",false);

                        //for each appointment the occupying patient has, find it in the checkin list and remove it from FB
                        angular.forEach($scope.checkins,function (matchingPatient)
                        {
                            if(matchingPatient.PatientId === id.PatientId)
                            {
                                $scope.logMessage("force_remove_FB","General","Function call on Patient "+ matchingPatient.PatientId +" with appointment serial "+ matchingPatient.AppointmentId + matchingPatient.CheckinSystem);

                                $scope.removeFromFB(matchingPatient);
                            }
                        });
                    }
                });

                if(callCondition == "ASSIGN EXAM ROOM") {
                    $scope.sendToLocation(patient,selectedLocation.Name,false);
                }
                else {
                    $scope.callPatientToSelectedLocation(patient,selectedLocation,true);
                }
            });
        }
    };

    //initialize the modal function that lets us select resources/locations
    $scope.openSelectorModal = function(options,selectedOptions,title)
    {
        $uibModal.open(
        {
            animation: true,
            templateUrl: 'VirtualWaitingRoom/js/vwr/templates/selectorModal.htm',
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
    $scope.openQuestionnaireModal = function(patient)
    {
        $uibModal.open(
        {
            animation: true,
            templateUrl: 'VirtualWaitingRoom/js/vwr/templates/questionnaireModal.htm',
            controller: questionnaireModalController,
            windowClass: 'questionnaireModal',
            size: 'lg',
            //backdrop: 'static',
            resolve:
            {
                patient: function() {return patient;}
            }
        });
    }


    //=========================================================================
    // Open the SMS modal
    //=========================================================================
    $scope.openSMSRegistrationModal = function(patient)
    {
        $uibModal.open(
        {
            animation: true,
            templateUrl: 'VirtualWaitingRoom/js/vwr/templates/registerSMSModal.htm',
            controller: registerSMSModalController,
            windowClass: 'registerSMSModal',
            size: 'lg',
            //backdrop: 'static',
            resolve:
            {
                patient: function() {return patient;}
            }
        });
    }


    //=========================================================================
    // Open the weigh patient modal
    //=========================================================================
    $scope.openWeightModal = function(patient)
    {
        $uibModal.open(
        {
            animation: true,
            templateUrl: 'VirtualWaitingRoom/js/vwr/templates/weightModal.htm',
            controller: weightModalController,
            windowClass: 'weightModal',
            size: 'lg',
            backdrop: 'static',
            resolve:
            {
                patient: function() {return patient;}
            }
        }).result.then(function(response)
        {
            //if the weight was updated, remove the patient from the ToBeWeighed FB array
            if(response)
            {
                //create object for older browsers ({[varName]: 1} syntax doesn't work)
                var tempObj = {};
                tempObj[patient.PatientId + patient.CheckinSystem] = {};

                firebaseScreenRef.child("ToBeWeighed").update(tempObj);

                $scope.logMessage("remove_weight_arr","General","Patient "+ patient.PatientId +" with appointment serial "+ patient.AppointmentId + patient.CheckinSystem +" removed from weight array in firebase "+ $scope.pageSettings.ClinicHubId);
            }
        });
    }

    //=========================================================================
    // Open the Diagnosis modal
    //=========================================================================
    $scope.openDiagnosisModal = function(patient)
    {
        $uibModal.open({
            animation: true,
            templateUrl: 'VirtualWaitingRoom/js/vwr/templates/diagnosisModal.htm',
            controller: diagnosisModalController,
            windowClass: 'diagnosisModal',
            size: 'lg',
            //backdrop: 'static',
            resolve:
            {
                patient: function() {return patient;}
            }
        })
    }

    $scope.loadPatientDiagnosis = function(patient)
    {
        $http({
            url: "php/api/private/v1/patient/diagnosis/getPatientDiagnosisList",
            method: "GET",
            params: {
                patientId: patient.PatientId
            }
        })
        .then(res => {
            $scope.lastPatientDiagnosisList = res.data.data
        });
    }

    //=========================================================================
    // Open the form modal
    //=========================================================================
    $scope.openFormModal = function(patient)
    {
        $scope.completeAppointment(patient);
    }

    $scope.logMessage = function(identifier,type,message)
    {
        $http({
            url: "php/api/private/v1/vwr/logMessage",
            method: "POST",
            data:
            {
                printJSON: 1,
                filename: 'VirtualWaitingRoom.html',
                identifier: identifier,
                type: type,
                message: message
            }
        });
    }

    //=========================================================================
    // Open the wearable charts modal dialog
    //=========================================================================
    $scope.showWearableDataCharts = async function(wearablesURL)
    {
        WearableCharts.showWearableDataCharts(wearablesURL);
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
            url: "php/api/private/v1/patient/sms/sendSmsForZoom",
            method: "POST",
            data:
            {
                patientId:  patient.PatientId,
                zoomLink:   $scope.pageSettings.zoomLink,
                resName:    patient.ResourceName
            }
        })
        .then( _ => {
            firebaseScreenRef.child("zoomLinkSent").update({[patient.AppointmentId]: 1});
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
        return $scope.screenRows["zoomLinkSent"].hasOwnProperty(patient.AppointmentId);
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
