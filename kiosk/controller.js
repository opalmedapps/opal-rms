var app = angular.module('myApp',[]);

app.config(['$locationProvider',function ($locationProvider)
{
    $locationProvider.html5Mode({
        enabled: true,
        requireBase: false,
        rewriteLinks : false
    });
}]);

app.controller('main', async function($scope,$http,$sce,$location,$timeout,$interval,$window)
{
    let params = $location.search();
    let kioskLocation = params.location ?? "DS1_1";

    $scope.pageProperties = {
        refreshAllowed:             true,
        displayNetworkWarning:      false,
        messageBackgroundColor:     (kioskLocation.includes("Reception")) ? "blue" : "rgb(51,153,51)"
    };

    $scope.messageComponents = generateDefaultMessageComponents(kioskLocation);

    //reload the page every once in a while to ensure that the kiosk is always running the latest version of the code
    //also check if the server is up before doing the refresh
    //if it's not, display a warning on the kiosk
    $interval(async _ => {
        let isServerOnline = await checkIfServerIsOnline();
        $scope.pageProperties.displayNetworkWarning = !isServerOnline;

        if($scope.pageProperties.refreshAllowed === true && isServerOnline === true) {
            $window.location.reload();
        }
    },5*60*1000);

    //log heartbeat message upon refresh
    // $interval(_ => {

    // },5*60*1000);

    //room to check the patient into
    let destination = "UNKNOWN";
    if(/^(DRC_1|DRC_2|DRC_3)$/.test(kioskLocation) === true) {
        destination = "D RC WAITING ROOM";
    }
    else if(/^(DRC_1|DRC_2|DRC_3)$/.test(kioskLocation) === true) {
        destination = "D S1 WAITING ROOM";
    }
    else if(/Reception/.test(kioskLocation) === true) {
        destination = kioskLocation.replace(" Reception","");
        destination = `${destination} WAITING ROOM`.toUpperCase();
    }

    $scope.scanPatient = async function(scannerInput)
    {
        //disable the page refresh
        $scope.pageProperties.refreshAllowed = false;

        let mrn = scannerInput;
        let ramq = scannerInput;

        //if there's any alphabetic characters in the input, then the input is a ramq
        if(/[a-zA-Z]/.test(mrn) === true) {
            mrn = null;
        }
        else {
            ramq = null;
        }

        let patient = await getPatientInfo(ramq,mrn,"RVH"); //RVH hardcoded for now

        //send to reception if the patient is unknown
        if(patient === null) {
            $scope.messageComponents = generateSendToReceptionMessageComponents(kioskLocation);
            $scope.$apply();
            $scope.messageComponents = await $timeout(_ => generateDefaultMessageComponents(kioskLocation),20*1000);
        }
        //if the patient has a ramq and it's expired, send them to admissions
        else if(dayjs().isAfter(patient.ramqExpiration)) {
            $scope.messageComponents = generateSendToAdmissionMessageComponents(kioskLocation);
            $scope.$apply();
            $scope.messageComponents = await $timeout(_ => generateDefaultMessageComponents(kioskLocation),20*1000);
        }
        //check in the patient if there's no issues
        else {
            $scope.messageComponents = generatePatientDisplayMessageComponents(kioskLocation,patient);
            $scope.$apply();

            //give the patient time to see their name on the screen
            await new Promise(r => setTimeout(r,4*1000));

            let appointment = await getNextAppointment();

            if(appointment.nextAppointment !== null) {
                sendSmsMessage(
                    patient,
                    "MUHC - Cedars Cancer Centre: You are checked in for your appointment(s).",
                    "CUSM - Centre du cancer des Cèdres: Votre(vos) rendez-vous est(sont) enregistré(s)"
                );

                centerImage = "";

                if(appointment.ariaPhotoOk === false) {
                    $scope.messageComponents = generateSendToGetPhotoMessageComponents(kioskLocation);
                }
                else {
                    $scope.messageComponents = generateCheckedInMessageComponents(kioskLocation);
                }

                //for NEXT appointment
                // $waitingRoom = null;
                // $waitingRoom = "TestCentre" if nextAppointment = "NS - prise de sang/blood tests pre/post tx"
                // $waitingRoom = "DS1" if $hasAriaAppointment

                // $middleMessage_image         = "<img src=\"$images/salle_DS1.png\">" if($DestinationWaitingRoom eq "DS1");
                // $middleMessage_image         = "<img src=\"$images/$DestinationWaitingRoom.png\">" if($DestinationWaitingRoom eq "TestCentre");
            }
            else {
                sendSmsMessage(
                    patient,
                    "MUHC - Cedars Cancer Centre: Unable to check-in for one or more of your appointment(s). Please go to the reception.",
                    "CUSM - Centre du cancer des Cèdres: Impossible d'enregistrer un ou plusieurs de vos rendez-vous. SVP vérifier à la réception"
                );

                $scope.messageComponents = generateSendToReceptionMessageComponents(kioskLocation);
            }

            //   return (
//       $MV_CheckinStatus[0],
//       $MV_ScheduledStartTime[0],
//       $WaitingRoomWherePatientShouldWait,
//       $PhotoOk
//     );

// }

        }

        //force a DOM update
        $scope.$apply();

        //re-enable the page reload
        $scope.pageProperties.refreshAllowed = true;

        //multiple scans can stack and get resolved in random order

        //no arrows if patient was found...?

        //scanner is always on and it's always possible to enter input to trigger refresh

    }

    function generateDefaultMessageComponents(location)
    {
        // let centerImageWidth = (location.includes("Reception")) ? "214" : "614";
        // let centerImage = $sce.trustAsHtml(`<img border="1" width="${centerImageWidth}" src="/images/animation.gif">`);

        return {
            arrowImage:                 getArrowDirection(null,null),
            centerImage:                $sce.trustAsHtml(`<img border="1" style="width: 100%" src="/images/animation.gif">`),
            locationImage:              getLocationImage(location),
            mainMessage:                {
                english: "Check in",
                french: "Enregistrement",
            },
            subMessage: {
                english: $sce.trustAsHtml("<center>Please enter the patient MRN to check in <br></center>"),
                french: $sce.trustAsHtml("<center>Veuillez entrer le numero de dossier medical du patient pour l'enregistrer <br></center>"),
            }
        };
    }

    function generateSendToReceptionMessageComponents(location)
    {
        return {
            arrowImage:                 getArrowDirection(location,"Reception"),
            centerImage:                $sce.trustAsHtml(`<img src="/images/Reception_generic.png">`),
            locationImage:              getLocationImage(location),
            mainMessage: {
                english: "Please go to the reception",
                french: "Vérifier à la réception",
            },
            subMessage: {
                english: $sce.trustAsHtml("Unable to check you in at this time"),
                french: $sce.trustAsHtml("Impossible de vous enregistrer en ce moment"),
            }
        };
    }

    function generateSendToGetPhotoMessageComponents(location)
    {
        return {
            arrowImage:                 getArrowDirection(null,null),
            centerImage:                $sce.trustAsHtml(`<img src="/images/Reception_generic.png">`),
            locationImage:              getLocationImage(location),
            mainMessage: {
                english: "Please go to the reception",
                french: "Vérifier à la réception",
            },
            subMessage: {
                english: $sce.trustAsHtml(`Please go to the reception <span style="background-color: rgb(255,255,224)"><b><font color='red'>to have your photo taken.</font></b></span><b></b>`),
                french: $sce.trustAsHtml(`Veuillez vous présenter à la réception <span style="background-color: rgb(255,255,224)"><b><font color='red'><b>pour que l'on vous prenne en photo.</font></b></span>`),
            }
        };
    }

    function generateSendToAdmissionMessageComponents(location)
    {
        return {
            arrowImage:                 getArrowDirection(null,null),
            centerImage:                $sce.trustAsHtml(`<img style="width: 100%" src="/images/RV_Admissions.png">`),
            locationImage:              getLocationImage(location),
            mainMessage: {
                english: "Hospital Card Expired",
                french: "Carte d'hôpital expirée",
            },
            subMessage: {
                english: $sce.trustAsHtml(`<span style="background-color: red">Unable to check you in at this time.</span> <span style="background-color: yellow">Please go to Admitting at <b>C RC.0046</b> to renew your hospital card.</span><br>`),
                french: $sce.trustAsHtml(`<span style="background-color: red">Impossible de vous enregistrer en ce moment.</span> <span style="background-color: yellow">Veuillez vous rendre au bureau des admissions à <b>C RC.0046</b> pour renouveler votre carte d'hôpital.</span><br>`),
            }
        };
    }

    function generateCheckedInMessageComponents(location,destination)
    {
        return {
            arrowImage:                 getArrowDirection(null,null),
            centerImage:                null,
            locationImage:              getLocationImage(location),
            mainMessage: {
                english: "You are Checked In",
                french: "Vous êtes enregistré",
            },
            subMessage: {
                english: $sce.trustAsHtml(`<span style="background-color: yellow">You are checked in. Please leave the Cancer Centre and wait to be called by SMS or come back only 5 minutes before your appointment time.</span>`),
                french: $sce.trustAsHtml(`<span style="background-color: yellow">Vous êtes enregistré. Veuillez sortir du Centre de cancer et attendre d'être appelé par SMS ou retourner 5 minutes avant votre rendez-vous.</span>`),
            }
        };
    }

    function generatePatientDisplayMessageComponents(location,patient)
    {
        let displayName = patient.firstName +" "+ patient.lastName.substring(0,3) +"****";

        return {
            arrowImage:                 getArrowDirection(null,null),
            centerImage:                $sce.trustAsHtml(`<img src="/images/Measles.png"><p>`),
            locationImage:              getLocationImage(location),
            mainMessage: {
                english: "Please wait...",
                french: "Veuillez patienter...",
            },
            subMessage: {
                english: $sce.trustAsHtml(`Retrieving information for <span style="background-color: yellow">${displayName}</span>`),
                french: $sce.trustAsHtml(`Récuperation de données <span style="background-color: yellow">${displayName}<span>`),
            }
        };
    }

    function getLocationImage(location)
    {
        let locationImages = {
            DRC_1: "/images/DRC_1_Alone.gif",
            DRC_2: "/images/DRC_2_Alone.gif",
            DRC_3: "/images/DRC_3_Alone.gif",
            DS1_1: "/images/DS1_1_Alone.gif",
            DS1_2: "/images/DS1_2_Alone.gif",
            DS1_3: "/images/DS1_1_Alone.gif",
        };

        return locationImages[location] ?? null;
    }

    function getArrowDirection(location,destination)
    {
        let arrows = {
            DRC_1: {
                DS1:        "/images/arrow_down.png",
                Reception:  "/images/arrow_right.png",
                TestCentre: "/images/arrow_hereRC.png",
            },
            DRC_2: {
                DS1:        "/images/arrow_down.png",
                Reception:  "/images/arrow_left.png",
                TestCentre: "/images/arrow_up_left.png",
            },
            DRC_3: {
                DS1:        "/images/arrow_down.png",
                Reception:  "/images/arrow_left.png",
                TestCentre: "/images/arrow_right.png",
            },
            DS1_1: {
                DS1:        "/images/arrow_here.png",
                Reception:  "/images/arrow_right.png",
                TestCentre: "/images/arrow_up.png",
            },
            DS1_2: {
                DS1:        "/images/arrow_left.png",
                Reception:  "/images/arrow_left.png",
                TestCentre: "/images/arrow_up.png",
            },
            DS1_3: {
                DS1:        "/images/arrow_left.png",
                Reception:  "/images/arrow_left.png",
                TestCentre: "/images/arrow_up.png",
            },
        };

        return arrows?.[location]?.[destination] ?? "/images/arrow_hereDefault.png";
    }

    async function checkIfServerIsOnline()
    {
        return $http({
            url: "/kiosk",
            method: "GET",
        })
        .then(_ => true)
        .catch(_ => false);
    }

    async function getPatientInfo(ramq,mrn,site)
    {
        return $http({
            url: "/php/api/private/v1/patient/findPatient",
            method: "POST",
            data: {
                mrn:  mrn,
                site: site,
                ramq: ramq,
            }
        })
        .then(r => {
            p = r.data.data[0] ?? null;

            if(p === null) {
                return null;
            }

            return {
                firstName:          p.first,
                lastName:           p.last,
                patientId:          p.patientId,
                ramqExpiration:     p.ramqExp,
            };
        })
        .catch(_ => null);
    }

    async function getNextAppointment(patient,destination)
    {
        return $http({
            url: "/php/api/private/v1/patient/checkInViaKiosk.php",
            method: "POST",
            data: {
                patientId:  patient.patientId,
                room:       destination,
            }
        })
        .then(r => ({
            ariaPhotoOk:     r.data.data.ariaPhotoOk,
            nextAppointment: r.data.data.nextAppointment
        }))
        .catch(_ => ({
            ariaPhotoOk:     null,
            nextAppointment: null
        }));
    }

    function sendSmsMessage(patient,messageEN,messageFR)
    {
        $http({
            url: "/php/api/private/v1/patient/sms/sendSms",
            method: "POST",
            data: {
                patientId:  patient.patientId,
                messageEN:  messageEN,
                messageFR:  messageFR
            }
        });
    }

    function logEvent()
    {
        //message to log
        // $log_message = "default message, $location, $subMessage_en"; //default message
        // $log_message = "$PatientId, $location, $subMessage_en"; //when finding patient and checking in
        // $log_message         = "Problem detected - sending to reception - $PatientId, $location, $subMessage_en"; // on error

        //no leading zeroes for month/day
        // my $now = "$month_today/$day_today/$year_today $hour_today:$min_today:$sec_today";
        // print CHECKINLOG "###$now, $log_message, $DestinationWaitingRoom, $direction, $subMessage_en\n\n";

        $http({
            url: "/php/api/private/v1/vwr/logMessageForKiosk",
            method: "POST",
            data: {
                patientId:  patientId,
                room:       destination,
            }
        });
    }

});
