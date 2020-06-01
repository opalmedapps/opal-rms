//screen controller
myApp.controller('screenDisplayController',async function($scope,$http,$firebaseArray,$interval,ngAudio,$location)
{
	//=========================================================================
	// General useful stuff
	//=========================================================================

	//=========================================================================
	// Setup the audio using ngAudio
	//=========================================================================
	$scope.audio = ngAudio.load('sounds/magic.wav');

	//=========================================================================
	// Set the firebase connection
	//=========================================================================
	// Get today's date as we create a new firebase each day so as to log events
	var today = new Date();
	var dd = today.getDate();
	var mm = today.getMonth()+1; //January is 0!
	var yyyy = today.getFullYear();

	if(dd<10) {dd='0'+dd;}

	if(mm<10) {mm='0'+mm;}

	today = mm+'-'+dd+'-'+yyyy;

	//get the screen's location from the url
	var urlParams = $location.search();

	$scope.clinicalArea = urlParams.location;

	$scope.firebaseCopy; //copy of the firebase array; used to prevent encrypted names from showing up

	//every 10 minutes, check the time
	//if its late at night, turn the screen black
	$scope.currentLogo = "";

	$scope.checkTime = function()
	{
		var currentDate = new Date();
        var currentTime = currentDate.getHours();

		if(currentTime >= 20 || currentTime < 6) {
			$scope.currentLogo = "./images/black.jpg";
		}
		else {
			$scope.currentLogo = "./images/Banner_treatments.png";
		}
	}
	$scope.checkTime();

	$interval($scope.checkTime(),1000*60*10);

    $scope.tickerText = "Notifications par texto pour vos RDV maintenant disponibles! Abonnez-vous à la réception... / Appointment SMS notifications are now available! You can register at the reception...";

	//define specific rooms that should display with a left arrow on the screen
	//this is to guide the patient to the right area
	// $scope.leftArrowLocations = ["RT TX ROOM 1","RT TX ROOM 2","RT TX ROOM 3","RT TX ROOM 4","RT TX ROOM 5","RT TX ROOM 6","CyberKnife"];

    // $scope.rightArrowLocations = ["SS1 EXAM ROOM","SS2 EXAM ROOM","SS3 EXAM ROOM","SS4 EXAM ROOM","SS5 EXAM ROOM","SS6 EXAM ROOM","SS7 EXAM ROOM","SS8 EXAM ROOM","SS9 EXAM ROOM","SS10 EXAM ROOM","SS11 EXAM ROOM","SS12 EXAM ROOM","SS13 EXAM ROOM"];

    let firebaseSettings = await getFirebaseSettings();

	//connect to firebase
	var FirebaseUrl = firebaseSettings.FirebaseUrl + $scope.clinicalArea + "/" + today;

	var firebaseScreenRef = new Firebase(FirebaseUrl); // FB JK
	console.log("firebaseScreenRef", FirebaseUrl);

	firebaseScreenRef.authWithCustomToken(firebaseSettings.FirebaseSecret, function(error,result)
	{
		if(error) {console.log("Authentication Failed!", error);}
		else
		{
			//console.log("Authenticated successfully with payload:", result.auth);
			//console.log("Auth expires at:", new Date(result.expires * 1000));
		}
	});

	//=========================================================================
	// Get the data from Firebase and load it into an array called screenRows
	// When the data changes on Firebase this array will be automatically updated
	//=========================================================================
	$scope.screenRows = $firebaseArray(firebaseScreenRef);
	$scope.screenRows.$loaded().then(function()
	{
		console.log($scope.screenRows);
	});

	$scope.decryptedPatientTimestamp = new Array(); //used to track if the patient object has changed

	//=========================================================================
	// Function to decrypt all data in the screen rows array
	//=========================================================================
	function decryptData (screenRows)
	{
		// decrypt each row one by one
		angular.forEach(screenRows,function (patObj,index)
		{
			// Get the patient's current timestamp, if it is different than his/her
			// previously-recorded timestamp, then we need to decrypt
			// otherwise, the previous name is fine as it was already decrypted
			var Timestamp = patObj.Timestamp;
			var identifier = patObj.$id;

			if($scope.decryptedPatientTimestamp[identifier] != Timestamp)
			{
				var bytes  = CryptoJS.AES.decrypt(screenRows[index].FirstName,'secret key 123');
				var firstName_text = bytes.toString(CryptoJS.enc.Utf8);

				// Set the name of this patient to be the decrypted name
				patObj.FirstName = firstName_text;
				$scope.decryptedPatientTimestamp[identifier] = Timestamp;
			}
		});
	} // end of decryptData function

	$scope.screenRows.$loaded().then(function() //wait until the array has been loaded from firebase
	{
		decryptData($scope.screenRows) //if there are any patients in the array on load, we decrypt them
		$scope.firebaseCopy = angular.copy($scope.screenRows);

		//=========================================================================
		// Watch the number of patients in the firebase list. When it changes,
		// play a sound.
		//=========================================================================
		$scope.$watch(function() {return $scope.screenRows.length}, function (newValue,oldValue)
		{
			// Play sound if the number of patients is increased in the array
			// oldValue is initially 0, so don't play a sound on page load
			if(newValue > oldValue && oldValue >= 2) //we have to count the ToBeWeighed and Metadata rows
			{
				$scope.audio.play();
			}
		}); // end of watch for number of patients increaing in list

		//=========================================================================
		// Every time the screenRows array is updated on Firebase we need to send the
		// data for decryption
		// We know an update has occured when the timestamp in the Metadata changes
		//=========================================================================

		$scope.screenRows.$loaded().then(function() //wait until the array has been loaded from firebase or we'll just get null when trying to get the Metadata
		{
			$scope.$watch(function()
			{
				if(!$scope.screenRows.$getRecord("Metadata")) {return null;}
				else {return $scope.screenRows.$getRecord("Metadata").LastUpdated;}
			}, function (newValue,oldValue)
			{
				if(newValue != oldValue)
				{
					decryptData($scope.screenRows);
					$scope.firebaseCopy = angular.copy($scope.screenRows);
				}
			}); // end of watch
		});
    });

    async function getFirebaseSettings()
    {
        return $http({
            url: "php/getFirebaseSettings.php",
            method: "GET"
        }).then( result => result.data);
    }
});
