var app = angular.module('myApp',[]);

app.config(['$locationProvider',function ($locationProvider)
{
    $locationProvider.html5Mode({
        enabled: true,
        requireBase: false,
        rewriteLinks : false
    });
}]);

app.controller('main', async function($scope,$http,$sce,$location,$interval,$window)
{
    let params = $location.search();
    let kioskLocation = params.location ?? "DS1_1";

    let locationIsReception = /Reception/.test(kioskLocation);

    //room to check the patient into
    let checkInRoom = "UNKNOWN";
    if(/^(DRC_1|DRC_2|DRC_3)$/.test(kioskLocation) === true) {
        checkInRoom = "D RC WAITING ROOM";
    }
    else if(/^(DS1_1|DS1_2|DS1_3)$/.test(kioskLocation) === true) {
        checkInRoom = "D S1 WAITING ROOM";
    }
    else if(locationIsReception === true) {
        checkInRoom = kioskLocation.replace(" Reception","");
        checkInRoom = `${checkInRoom} WAITING ROOM`.toUpperCase();
    }

    $scope.pageProperties = {
        refreshAllowed:             true,
        displayNetworkWarning:      false,
        messageBackgroundColor:     locationIsReception ? "blue" : "rgb(51,153,51)",
        locationDisplay:            kioskLocation.replace("_","-"),
        kioskHeight:                locationIsReception ? "98%" : null
    };

    $scope.receptionMessage = {
        showMessage: false,
        message:     null
    };

    $scope.messageComponents = generateDefaultMessageComponents();

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

    //always keep the input box focused when it's available
    $scope.$watch("pageProperties.refreshAllowed",async (newValue) => {
        if(newValue === true) {
            await sleep(0.2);
            document.getElementById("scannerBar").focus();
        }
    })

    //also refocus periodically in case someone clicked outside the input box
    $interval(_ => {
        document.getElementById("scannerBar").focus();
    },10*1000);

    //log heartbeat message upon refresh
    logEvent(null,null,$scope.messageComponents);

    // load the scheduler
    let schedule = [];
    $http({
        url: "/tmp/schedule.csv",
        method: "GET",
    }).then(function(response) {
        schedule = $.csv.toObjects(response.data).map(x => ({
            weekday: x.Weekday,
            code:    x["Clinic Code"],
            level:   x.Level
        })).filter(x => x.weekday === dayjs().format("dddd"));
    });

    $scope.processScannerInput = async function(scannerInput)
    {
        //disable the page refresh
        $scope.pageProperties.refreshAllowed = false;
        let destination = null;

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
            await sleep(20);
        }
        //if the patient has a ramq and it's expired, send them to admissions
        else if(dayjs().isAfter(patient.ramqExpiration)) {
            $scope.messageComponents = generateSendToAdmissionMessageComponents();
            $scope.$apply();
            await sleep(20);
        }
        //check in the patient if there's no issues
        else {
            $scope.messageComponents = generatePatientDisplayMessageComponents(patient);
            $scope.$apply();

            //give the patient time to see their name on the screen
            await sleep(4);

            let appointment = await getNextAppointment(patient,checkInRoom);

            if(appointment.nextAppointment !== null) {
                destination = checkInRoom;

                sendSmsMessage(
                    patient,
                    "MUHC - Cedars Cancer Centre: You are checked in for your appointment(s).",
                    "CUSM - Centre du cancer des Cèdres: Votre(vos) rendez-vous est(sont) enregistré(s)"
                );

                if(appointment.ariaPhotoOk === false) {
                    $scope.messageComponents = generateSendToGetPhotoMessageComponents(kioskLocation);
                }
                else {
                    $scope.messageComponents = generateCheckedInMessageComponents(kioskLocation,appointment.nextAppointment);
                }

                if(locationIsReception === true) {
                    $scope.receptionMessage.showMessage = true;
                    $scope.receptionMessage.message = $sce.trustAsHtml(`Patient has been checked into appointment <i>${appointment.nextAppointment.name}</i> at <b>${appointment.nextAppointment.datetime}</b>... status: OK`);
                }
            }
            else {
                sendSmsMessage(
                    patient,
                    "MUHC - Cedars Cancer Centre: Unable to check-in for one or more of your appointment(s). Please go to the reception.",
                    "CUSM - Centre du cancer des Cèdres: Impossible d'enregistrer un ou plusieurs de vos rendez-vous. SVP vérifier à la réception"
                );

                $scope.messageComponents = generateSendToReceptionMessageComponents(kioskLocation);
            }

            $scope.$apply();
            await sleep(11);
        }

        //log the event
        logEvent(scannerInput,destination,$scope.messageComponents);

        //re-enable the page reload
        $scope.pageProperties.refreshAllowed = true;

        //return to the default view
        if(locationIsReception === true) {
            $scope.receptionMessage.showMessage = false;
            $scope.receptionMessage.message = null;
        }

        $scope.messageComponents = generateDefaultMessageComponents();
        $scope.$apply();
    }

    function generateDefaultMessageComponents()
    {
        return {
            arrowImage:                 null,
            centerImage:                "/images/animation.gif",
            mainMessage: {
                english: "Check in",
                french: "Enregistrement",
            },
            subMessage: {
                english: $sce.trustAsHtml("Please enter the patient MRN to check in."),
                french: $sce.trustAsHtml("Veuillez entrer le numero de dossier medical du patient pour l'enregistrer."),
            }
        };
    }

    function generateSendToReceptionMessageComponents(location)
    {
        return {
            arrowImage:                 getArrowImage(location,"Reception"),
            centerImage:                "/images/Reception_generic.png",
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
            arrowImage:                 getArrowImage(location,"Reception"), //overwrite if not on DS1
            centerImage:                "/images/Reception_generic.png",
            mainMessage: {
                english: "Please go to the reception",
                french: "Vérifier à la réception",
            },
            subMessage: {
                english: $sce.trustAsHtml(`Please go to the reception <span style="background-color: rgb(255,255,224); color: "red"; font-weight: "bold";>to have your photo taken.</span>`),
                french: $sce.trustAsHtml(`Veuillez vous présenter à la réception <span style="background-color: rgb(255,255,224); color: "red"; font-weight: "bold";">pour que l'on vous prenne en photo.`),
            }
        };
    }

    function generateSendToAdmissionMessageComponents()
    {
        return {
            arrowImage:                 null,
            centerImage:                "/images/RV_Admissions.png",
            mainMessage: {
                english: "Hospital Card Expired",
                french: "Carte d'hôpital expirée",
            },
            subMessage: {
                english: $sce.trustAsHtml(`<span style="background-color: red">Unable to check you in at this time.</span> <span style="background-color: yellow">Please go to Admitting at <b>C RC.0046</b> to renew your hospital card.</span>`),
                french: $sce.trustAsHtml(`<span style="background-color: red">Impossible de vous enregistrer en ce moment.</span> <span style="background-color: yellow">Veuillez vous rendre au bureau des admissions à <b>C RC.0046</b> pour renouveler votre carte d'hôpital.</span>`),
            }
        };
    }

    function generateCheckedInMessageComponents(location,appointment)
    {
        let destination = null;
        let centerImage = null

        if(appointment.name === "NS - prise de sang/blood tests pre/post tx") {
            destination = "TestCentre";
            centerImage = "/images/TestCentre.png";
        }

        if(appointment.sourceSystem === "Aria") {
            destination = "DS1";
            centerImage = "/images/salle_DS1.png";
        }

        let scheduledMatch = schedule.filter(x => appointment.code.includes(x.code)).at(-1);
        if(scheduledMatch !== undefined) {
            destination = scheduledMatch.level;

            if(destination === "DRC") {
                centerImage = "/images/salle_DRC.png";
            }
        }

        return {
            arrowImage:                 getArrowImage(location,destination),
            centerImage:                centerImage,
            mainMessage: {
                english: "You are Checked In",
                french: "Vous êtes Enregistré",
            },
            subMessage: {
                english: $sce.trustAsHtml(`<span style="background-color: yellow">You are checked in. Please leave the Cancer Centre and wait to be called by SMS or come back only 5 minutes before your appointment time.</span>`),
                french: $sce.trustAsHtml(`<span style="background-color: yellow">Vous êtes enregistré. Veuillez sortir du Centre de cancer et attendre d'être appelé par SMS ou retourner 5 minutes avant votre rendez-vous.</span>`),
            }
        };
    }

    function generatePatientDisplayMessageComponents(patient)
    {
        let displayName = patient.firstName +" "+ patient.lastName.substring(0,3) +"****";

        return {
            arrowImage:                 null,
            centerImage:                "/images/Measles.png",
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

    function getArrowImage(location,destination)
    {
        let arrows = {
            DRC_1: {
                DS1:        "down",
                Reception:  "right",
                TestCentre: "hereRC",
            },
            DRC_2: {
                DS1:        "down",
                Reception:  "left",
                TestCentre: "up_left",
            },
            DRC_3: {
                DS1:        "down",
                Reception:  "left",
                TestCentre: "right",
            },
            DS1_1: {
                DS1:        "here",
                DRC:        "up",
                Reception:  "right",
                TestCentre: "up",
            },
            DS1_2: {
                DS1:        "here",
                DRC:        "up",
                Reception:  "left",
                TestCentre: "up",
            },
            DS1_3: {
                DS1:        "here",
                DRC:        "up",
                Reception:  "left",
                TestCentre: "up",
            },
        };

        direction = arrows?.[location]?.[destination] ?? null;

        return (direction === null) ? null : `/images/arrow_${direction}.png`;
    }

    function checkIfServerIsOnline()
    {
        return $http({
            url: window.location.href,
            method: "GET",
        })
        .then(_ => true)
        .catch(_ => false);
    }

    function getPatientInfo(ramq,mrn,site)
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

    function getNextAppointment(patient,destination)
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
            nextAppointment: {
                name:           r.data.data.nextAppointment.name,
                code:           r.data.data.nextAppointment.code,
                datetime:       r.data.data.nextAppointment.datetime,
                sourceSystem:   r.data.data.nextAppointment.sourceSystem
            }
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

    function logEvent(input,destination,messageComponents)
    {
        $http({
            url: "/php/api/private/v1/vwr/logMessageForKiosk",
            method: "POST",
            data: {
                input:              input,
                location:           kioskLocation,
                destination:        destination,
                centerImage:        messageComponents.centerImage,
                arrowDirection:     messageComponents.arrowImage,
                message:            $sce.getTrustedHtml(messageComponents.subMessage.english)
            }
        });
    }

    function sleep(seconds)
    {
        return new Promise(_ => setTimeout(_,seconds*1000));
    }

});
