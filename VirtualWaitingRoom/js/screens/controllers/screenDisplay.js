//screen controller
myApp.controller('screenDisplayController',async function($scope,$http,$firebaseArray,$interval,ngAudio,$location)
{
    //every 10 minutes, check the time
    //if its late at night, turn the screen black
    $scope.currentLogo = "";

    function checkTime()
    {
        let hour = dayjs().hour();

        if(hour >= 20 || hour < 6) {
            $scope.currentLogo = "./images/black.jpg";
        }
        else {
            $scope.currentLogo = "./images/Banner_treatments.png";
        }
    }
    checkTime();

    $interval(checkTime,1000*60*10);

    // $scope.tickerText = "Notifications par texto pour vos RDV maintenant disponibles! Abonnez-vous à la réception... / Appointment SMS notifications are now available! You can register at the reception...";
    $scope.tickerText = "Patients and caregivers are welcome to join our (ONLINE) group workshops. Contact us by phone (514) 934-1934 ext. 35297 or email us at cedarscansupport@muhc.mcgill.ca. / Patients et proches aidants sont les bienvenus dans nos ateliers (En LIGNE) de groupe. Communiquez avec nous par téléphone, au 514-934-1934 (poste 35297) ou par courriel à l’adresse suivante : cedarscansupport@muhc.mcgill.ca.";

    //define specific rooms that should display with a left arrow on the screen
    //this is to guide the patient to the right area
    $scope.leftArrowLocations = ["RADIATION TREATMENT ROOM 1","RADIATION TREATMENT ROOM 2","RADIATION TREATMENT ROOM 3","RADIATION TREATMENT ROOM 4","RADIATION TREATMENT ROOM 5","RADIATION TREATMENT ROOM 6","CyberKnife"];
    $scope.rightArrowLocations = ["DS1-A EXAM ROOM","DS1-B EXAM ROOM","DS1-C EXAM ROOM","DS1-D EXAM ROOM","DS1-E EXAM ROOM","DS1-F EXAM ROOM","DS1-G EXAM ROOM","DS1-H EXAM ROOM","DS1-J EXAM ROOM","DS1-K EXAM ROOM","DS1-L EXAM ROOM","DS1-M EXAM ROOM","DS1-N EXAM ROOM"];

    // Setup the audio using ngAudio
    let audio = ngAudio.load('sounds/magic.wav');

    // Set the firebase connection
    //get the screen's location from the url
    let urlParams = $location.search();

    //connect to Firebase
    let firebaseSettings = await getFirebaseSettings();
    let firebaseScreenRef = new Firebase(firebaseSettings.FirebaseUrl + urlParams.location + "/" + dayjs().format("MM-DD-YYYY"));

    firebaseScreenRef.authWithCustomToken(firebaseSettings.FirebaseSecret, error => {
        if(error !== null) {
            console.log("Authentication Failed!", error);
        }
    });

    //get the data from Firebase and load it into an array called screenRows
    //when the data changes on Firebase this array will be automatically updated
    let firebasePatients = $firebaseArray(firebaseScreenRef);

    $scope.patientList = []; //copy of the firebase array; used to prevent encrypted names from showing up while decrypting

    firebasePatients.$loaded().then(_ => { //wait until the array has been loaded from firebase
        $scope.patientList = decryptData(firebasePatients) //if there are any patients in the array on load, we decrypt them

        //watch the number of patients in the firebase list. When it changes, play a sound
        $scope.$watch(_ => firebasePatients.length, (newValue,oldValue) => {
            //play sound if the number of patients is increased in the array
            //oldValue is initially 0, so don't play a sound on page load
            if(newValue > oldValue && oldValue >= 2) { //we have to account for the ToBeWeighed and Metadata rows
                audio.play();
            }
        });

        //every time the screenRows array is updated on Firebase we need to send the data for decryption
        //we know an update has occured when the timestamp in the Metadata changes
        $scope.$watch(_ => firebasePatients.$getRecord("Metadata")?.LastUpdated ?? null, (newValue,oldValue) => {
            if(newValue !== oldValue) {
                $scope.patientList = decryptData(firebasePatients);
            }
        });
    });

    //decrypts all patient encrypted data in the firebase array
    function decryptData(screenRows)
    {
        return angular.copy(screenRows).map(x => {
            x.FirstName = CryptoJS.AES.decrypt(x.FirstName,"secret key 123").toString(CryptoJS.enc.Utf8);
            return x;
        });
    }

    async function getFirebaseSettings()
    {
        return $http({
            url: "/php/api/private/v1/vwr/getFirebaseSettings",
            method: "GET"
        }).then( result => result.data);
    }
});
