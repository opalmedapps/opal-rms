// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

//screen controller
myApp.controller("screenDisplayController", async function(
    $scope, $http, $firebaseArray, $interval, $location, $window, ngAudio, CONFIG
){
    let today = dayjs();
    let hour = today.hour();

    $scope.screenDisplayBackground = CONFIG.BRANDING_SCREEN_DISPLAY_BACKGROUND_PATH;

    //if its late at night, turn the screen black
    $scope.currentLogo = CONFIG.BRANDING_SCREEN_DISPLAY_BANNER_PATH;

    if(hour >= 20 || hour < 6) {
        $scope.currentLogo = "VirtualWaitingRoom/images/black.jpg";
    }

    //reload the page every once in a while to ensure that the kiosk is always running the latest version of the code
    //also check if the server is up before doing the refresh
    $interval(async _ => {
        let isServerOnline = await checkIfServerIsOnline();

        if(isServerOnline === true) {
            $window.location.reload();
        }
    },5*60*1000);

    // $scope.tickerText = "Notifications par texto pour vos RDV maintenant disponibles! Abonnez-vous à la réception... / Appointment SMS notifications are now available! You can register at the reception...";
    $scope.tickerText = "Do you need a family doctor? Register online on gamf.gouv.qc.ca. If you need help, a volunteer can assist you at the Cedars CanSupport Resource Centre room, DRC-1329… / Besoin d’un médecin de famille ? Inscrivez-vous en ligne au gamf.gouv.qc.ca. Si vous avez besoin d’aide, un bénévole pourra vous aider au Centre de ressources CanSupport des Cèdres, salle DRC-1329…";

    if(today.format("dddd") === "Tuesday" && hour >= 12 && hour < 13) {        
        $scope.tickerText = "Are you enjoying the music today? Please donate to the HEALING NOTES Fund at cedars.ca. Thank you! / Aimez-vous la musique aujourd’hui? Faites un don: Fonds NOTES DE RÉCONFORT au cedars.ca. Merci!";    
    }
    //define specific rooms that should display with a left arrow on the screen
    //this is to guide the patient to the right area
    $scope.leftArrowLocations = ["RADIATION TREATMENT ROOM 1","RADIATION TREATMENT ROOM 2","RADIATION TREATMENT ROOM 3","RADIATION TREATMENT ROOM 4","RADIATION TREATMENT ROOM 5","RADIATION TREATMENT ROOM 6","CyberKnife"];
    $scope.rightArrowLocations = ["DS1-A EXAM ROOM","DS1-B EXAM ROOM","DS1-C EXAM ROOM","DS1-D EXAM ROOM","DS1-E EXAM ROOM","DS1-F EXAM ROOM","DS1-G EXAM ROOM","DS1-H EXAM ROOM","DS1-J EXAM ROOM","DS1-K EXAM ROOM","DS1-L EXAM ROOM","DS1-M EXAM ROOM","DS1-N EXAM ROOM"];

    // Setup the audio using ngAudio
    let audio = ngAudio.load("VirtualWaitingRoom/sounds/magic.wav");

    // Set the firebase connection
    //get the screen's location from the url
    let urlParams = $location.search();

    //connect to Firebase
    let firebaseSettings = await getFirebaseSettings();
    var firebaseScreenRef = firebase.initializeApp(firebaseSettings.FirebaseConfig);

    //get the data from Firebase and load it into an object
    //when the data changes on Firebase this array will be automatically updated
    var firebasePatients = $firebaseArray(firebaseScreenRef.database().ref($scope.pageSettings.FirebaseBranch + urlParams.location + "/" + today.format("MM-DD-YYYY")));

    $scope.patientList = []; //copy of the firebase array; used to prevent encrypted names from showing up while decrypting

    firebasePatients.$loaded().then(_ => { //wait until the array has been loaded from firebase
        $scope.patientList = decryptData(firebasePatients) //if there are any patients in the array on load, we decrypt them

        //watch the number of patients in the firebase list. When it changes, play a sound
        $scope.$watch(_ => $scope.patientList.length, (newValue,oldValue) => {
            //play sound if the number of patients is increased in the array
            //oldValue is initially 0, so don't play a sound on page load
            if(newValue > oldValue) {
                audio.play();
            }
        });

        //every time the firebase object is updated on Firebase we need to send the data for decryption
        //we know an update has occured when the timestamp in the Metadata changes
        $scope.$watch(_ => firebasePatients.$getRecord("Metadata")?.LastUpdated ?? null, (newValue,oldValue) => {
            if(newValue !== oldValue) {
                $scope.patientList = decryptData(firebasePatients);
            }
        });
    });

    //decrypts all patient encrypted data in the firebase array
    function decryptData(patients)
    {
        let patientsArr = patients.$getRecord("patients");
        patientsArr = Object.keys(patientsArr).filter(x => !["LastUpdated","$id","$priority"].includes(x)).map(x => patientsArr[x]);

        //filter checked out patients due to appointment completions
        patientsArr = patientsArr.filter(x => x.PatientStatus !== "CheckedOut");

        return angular.copy(Object.values(patientsArr)).map(x => {
            x.FirstName = CryptoJS.AES.decrypt(x.FirstName,"secret key 123").toString(CryptoJS.enc.Utf8);
            return x;
        });
    }

    async function checkIfServerIsOnline()
    {
        return $http({
            url: window.location.href,
            method: "GET",
        })
        .then(_ => true)
        .catch(_ => false);
    }

    async function getFirebaseSettings()
    {
        return $http({
            url: "php/api/private/v1/vwr/getFirebaseSettings",
            method: "GET"
        }).then( result => result.data);
    }
});
