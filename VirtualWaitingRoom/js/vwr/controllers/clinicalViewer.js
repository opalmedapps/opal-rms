let app = angular.module('vwr',['checklist-model','datatables','datatables.buttons','ui.bootstrap','jlareau.bowser','ngMaterial','ngCookies']);

app.controller('main', function($scope,$uibModal,$http,$filter,$interval,$cookies,DTOptionsBuilder,callScript,bowser)
{
    $scope.chromeDetected = bowser.name == 'Chrome' ? 1:0;
    if(!$scope.chromeDetected) {$('[type="date"]').datepicker({dateFormat: 'yy-mm-dd'});}

    $scope.today = new Date();

    let speciality = $cookies.get("speciality");

    //add datatables settings
    $scope.dtOptions = DTOptionsBuilder.newOptions();
    $scope.dtOptions.withOption('lengthMenu',[[25,50,100,-1],[25,50,100,'All']]);
    $scope.addPrintButton = function()
    {
        var clinics = "";
        for (i = 0; i < $scope.inputs.selectedclinics.length; i++) {
            clinics += "" +$scope.inputs.selectedclinics[i].Name + "";
            if(i< $scope.inputs.selectedclinics.length-1) clinics += ", ";
        }
        $scope.dtOptions.withButtons(
            [
                'csvHtml5',
                {
                    extend: 'print',
                    title: $scope.inputs.type == 'specific' ? clinics: 'All Appointments',
                    exportOptions: {
                        stripHtml: false
                    },
                    customize: function (win)
                    {
                        $(win.document.body)
                            //.css( 'font-size', '15pt' )
                            .prepend(`
                            <table style="font-size:20px">
                            <tr>
                                <td><b>Current Date: </b></td><td> ${$filter('date')($scope.today)}</td>
                            </tr>
                            <tr>
                                <td><b>Search Date: </b></td><td> ${$filter('date')($scope.sDateDisplay)} - ${$filter('date')($scope.eDateDisplay)} </td>
                            </tr>
                            <tr>
                                <td><b>Clinic Name: </b></td><td> ${($scope.hideType ? $scope.appName : "All")} </td>
                            </tr>
                            <tr>
                                <td><b>Options: </b></td>
                                <td>
                                    ${($scope.optionSelected.comp) ? "Completed" : ""}
                                    ${($scope.optionSelected.openn) ? "Open" : ""}
                                    ${($scope.optionSelected.canc) ? "Cancelled" : ""}
                                    ${($scope.optionSelected.arrived) ? "CheckedIn" : ""}
                                    ${($scope.optionSelected.notArrived) ? "NotCheckedIn" : ""}
                                </td>
                            </tr>
                            </table>
                        `);

                        //$(win.document).find('table').addClass('test').css('font-size','20pt');
                    }
                }
            ]);
    }

    $scope.convertDate = function (enteredDate)
    {
        let year = enteredDate.getFullYear();
        let month = enteredDate.getMonth()+1 >= 10 ? enteredDate.getMonth()+1 : "0" + (enteredDate.getMonth()+1);
        let day = enteredDate.getDate() >= 10 ? enteredDate.getDate() : "0" + enteredDate.getDate()
        let convDate = year + "-" + month + "-" + day;

        return convDate;
    }

    $scope.convertTime = function (enteredTime)
    {
        return ("0"+enteredTime.getHours()).slice(-2) + ":" + ("0"+enteredTime.getMinutes()).slice(-2);
    }

    //these functions will ensure that a button always stays checked so that a query doesn't return an empty list
    $scope.keepAppChecked = function (check) {
        if ($scope.inputs.comp == false && $scope.inputs.openn == false && $scope.inputs.canc == false) {
            $scope.inputs[check] = true;
        }
        $scope.isInputsChange = false;
        $scope.liveState = 'Paused'

    }

    $scope.keepOpalChecked = function (check) {
        if ($scope.inputs.opal == false && $scope.inputs.SMS == false) {
            $scope.inputs[check] = true;
        }
        $scope.isInputsChange = false;
        $scope.liveState = 'Paused'
    }

    $scope.keepPatChecked = function (check) {
        if($scope.inputs.arrived == false && $scope.inputs.notArrived == false) {
            $scope.inputs[check] = true;
        }
        $scope.isInputsChange = false;
        $scope.liveState = 'Paused'

    }

    $scope.inputchange = function(){
        $scope.isInputsChange = false;
        $scope.liveState = 'Paused';

    }

    $scope.test = {
        showMenu: 'Show Menu',
        emptyList: false
    };

    $scope.showMenuButton = function(){
        if($scope.test.showMenu == 'Show Menu') $scope.test.showMenu = 'Hide Menu';
        else $scope.test.showMenu = 'Show Menu';
    }


    $scope.reset = function (resetPressed)
    {
        $scope.sDate = new Date();
        $scope.eDate = new Date();

        $scope.sTime = new Date(1970,0,1,0,0,0);
        $scope.eTime = new Date(1970,0,1,23,59,0);

        $scope.inputs =
            {
                comp: true,
                openn: true, //the variable is named openn instead of openn so it doesn't interfere with jQuery's open function
                canc: false,
                arrived: true,
                notArrived: true,
                opal: true,
                SMS: true,
                type: 'all',
                ctype: 'all',
                dtype: 'all',
                /*specificType: {name: ''},
                cspecificType: {name: ''},
                dspecificType: {name: ''},*/
                selectedclinics: [],
                selectedcodes: [],
                selecteddiagnosis: [],
            }

        $scope.isInputsChange = false;
        $scope.liveMode = 'Live';
        $scope.liveState = 'Paused';
        $scope.test.emptyList = false;
        /*if(resetPressed)
        {
            $scope.inputs.type = 'specific';
            $scope.inputs.specificType = $scope.clinics[0];
            $scope.inputs.ctype = 'specific';
            $scope.inputs.cspecificType = $scope.codes[0];
            $scope.inputs.dtype = 'specific';
            $scope.inputs.dspecificType = $scope.diagnosis[0];
        }*/
    }

    $http.get("./php/clinicalViewer/resourceQuery.php?clinic="+speciality).then(function(response)
    {
        $scope.clinics = response.data;

       // $scope.inputs.specificType = $scope.clinics[0];
    });

    $http.get("./php/clinicalViewer/getAppointmentCode.php?clinic="+speciality).then(function(response)
    {
        $scope.codes = response.data;
       // $scope.inputs.cspecificType = $scope.codes[0];
    });

    $http.get("./php/clinicalViewer/getDiagnosis.php?").then(function(response)
    {
        $scope.diagnosis = response.data;
        //$scope.inputs.dspecificType = $scope.diagnosis[0];
    })

    $scope.runScript = function (firstTime)
    {
        if(firstTime) {
            $scope.reset();
            $scope.zoomLink = "";
            $scope.showLM = true;
        }

        $scope.sDateDisplay = $scope.sDate;
        $scope.eDateDisplay = $scope.eDate;

        $scope.sTimeDisplay = $scope.sTime;
        $scope.eTimeDisplay = $scope.eTime;

        var clinics = "";
        for (i = 0; i < $scope.inputs.selectedclinics.length; i++) {
            clinics += "" +$scope.inputs.selectedclinics[i].Name + "";
            if(i< $scope.inputs.selectedclinics.length-1) clinics += ", ";
        }
        $scope.appName = clinics;
        var codes = "";
        for (i = 0; i < $scope.inputs.selectedcodes.length; i++) {
            codes += "" +$scope.inputs.selectedcodes[i].Name + "";
            if(i< $scope.inputs.selectedcodes.length-1) codes += ", ";
        }
        $scope.appCodeName = codes;
        var diagnosis = "";
        for (i = 0; i < $scope.inputs.selecteddiagnosis.length; i++) {
            diagnosis += "" +$scope.inputs.selecteddiagnosis[i].Name + "";
            if(i< $scope.inputs.selecteddiagnosis.length-1) diagnosis += ", ";
        }
        $scope.appDiagnosis = diagnosis;

        $scope.optionSelected = angular.copy($scope.inputs);

        $scope.sameDate = false;
        if($scope.sDateDisplay.getTime() == $scope.eDateDisplay.getTime()) {$scope.sameDate = true;}

        $scope.showDiv = false;
        $scope.message = "Getting Data...";

        if($scope.inputs.type == 'all')
        {
            $scope.inputs.selectedclinics = [];
        }
        if($scope.inputs.ctype == 'all')
        {
            $scope.inputs.selectedcodes = [];
        }
        if($scope.inputs.dtype == 'all')
        {
            $scope.inputs.selecteddiagnosis = [];
        }

        $scope.isInputsChange = true;
        $scope.liveState= 'Live'
        $scope.showLM = true;
        if($scope.liveMode === 'Report'){
            $scope.showLM = false;
        }
        $scope.showMenu = 'false';


        callScript.getData($scope.convertDate($scope.sDate),$scope.convertDate($scope.eDate),$scope.convertTime($scope.sTime),$scope.convertTime($scope.eTime),$scope.inputs,speciality).then(function (response)
        {
            $scope.tableData = response;

            //filter all blood test appointments
            $scope.tableData = $scope.tableData.filter(function(app) {
                return !/blood/.test(app.appName);
            });

            if($scope.inputs.type == 'all') {$scope.titleLabel = 'All';}
            else if($scope.inputs.type == 'specific') {$scope.titleLabel = clinics;}

            $scope.message = "";
            $scope.hideType = 0;
            $scope.hideCodeType = 0;
            $scope.hideDiagnosisType = 0;
            if($scope.inputs.type == 'specific') {$scope.hideType = 1;}
            if($scope.inputs.ctype == 'specific') {$scope.hideCodeType = 1;}
            if($scope.inputs.dtype == 'specific') {$scope.hideDiagnosisType = 1;}
            $scope.showDiv = true;

            $scope.addPrintButton();
        });
    }

    $scope.openQuestionnaireModal = function (appoint)
    {
        var modalInstance = $uibModal.open(
            {
                animation: true,
                templateUrl: './js/vwr/templates/questionnaireLegacyModal.htm',
                controller: questionnaireLegacyModalController,
                windowClass: 'questionnaireLegacyModal',
                //size: 'lg',
                //backdrop: 'static',
                resolve:
                    {
                        patient: function() {return {'LastName': appoint.lname, 'FirstName': appoint.fname, 'PatientIdRVH': appoint.pID};}
                    }
            }).result.then(function(response)
        {

        });
    }

    $scope.openSelectorModal = function (options,selectedOptions,title)
    {
        var modalInstance = $uibModal.open(
            {
                animation: true,
                templateUrl: './js/vwr/templates/selectorModal.htm',
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
            if(($scope.inputs.type == 'specific' && $scope.inputs.selectedclinics.length == 0)||
            ($scope.inputs.ctype == 'specific' && $scope.inputs.selectedcodes.length == 0)||
                ($scope.inputs.dtype == 'specific' && $scope.inputs.selecteddiagnosis.length == 0))
                $scope.test.emptyList = true;
            else $scope.test.emptyList = false;

        });
        $scope.isInputsChange = false;
        $scope.liveState = 'Paused'

    }

    $scope.sendZoomLink = function(appoint)
    {
        if($scope.zoomLink.length> 10 || $scope.zoomLink.includes("zoom.us")) {
            $http({
                url: "php/sendSmsForZoom",
                method: "GET",
                params:
                    {
                        patientIdRVH: appoint.pID,
                        patientIdMGH: null,
                        zoomLink: $scope.zoomLink,
                        resName: appoint.appName
                    }
            });
            $scope.zoomLinkSent = true;
            alert("The message should have been sent.");
        }
        else{
            alert("Please paste your Zoom Personal Meeting ID URL on MSSS Zoom Link!");
        }
            //.then( _ => {
                //firebaseScreenRef.child("zoomLinkSent").update({[patient.Identifier]: 1});
            //});
    }

    $scope.openZoomLink = function()
    {
        $window.open($scope.zoomLink,"_blank");
    }

    $interval(function () {
        var clinics = "";
        for (i = 0; i < $scope.inputs.selectedclinics.length; i++) {
            clinics += "" +$scope.inputs.selectedclinics[i].Name + "";
            if(i< $scope.inputs.selectedclinics.length-1) clinics += ", ";
        }
        if($scope.isInputsChange && $scope.liveMode === 'Live'){
            callScript.getData($scope.convertDate($scope.sDate), $scope.convertDate($scope.eDate), $scope.convertTime($scope.sTime), $scope.convertTime($scope.eTime), $scope.inputs, speciality).then(function (response) {
                $scope.tableData = response;

                //filter all blood test appointments

                if ($scope.inputs.type == 'all') {
                    $scope.titleLabel = 'All';
                } else if ($scope.inputs.type == 'specific') {
                    $scope.titleLabel = clinics;
                }

                $scope.message = "";
                $scope.hideType = 0;
                $scope.hideCodeType = 0;
                $scope.hideDiagnosisType = 0;
                if($scope.inputs.type == 'specific') {$scope.hideType = 1;}
                if($scope.inputs.ctype == 'specific') {$scope.hideCodeType = 1;}
                if($scope.inputs.dtype == 'specific') {$scope.hideDiagnosisType = 1;}
                $scope.showDiv = true;

                $scope.addPrintButton();
            });
        }

    },180000);
});



app.factory('callScript',function($http,$q)
{
    return {
        getData: function(sDate,eDate,sTime,eTime,inputs,speciality)
        {
            let defer = $q.defer();
            var clinics = "";
            for (i = 0; i < inputs.selectedclinics.length; i++) {
                clinics += "\"" +inputs.selectedclinics[i].Name + "\"";
                if(i< inputs.selectedclinics.length-1) clinics += ",";
            }
            var codes = "";
            for (i = 0; i < inputs.selectedcodes.length; i++) {
                codes += "'"+inputs.selectedcodes[i].Name + "'";
                if(i< inputs.selectedcodes.length-1) codes += ",";
            }
            var diagnosis = "";
            for (i = 0; i < inputs.selecteddiagnosis.length; i++) {
                diagnosis += ""+inputs.selecteddiagnosis[i].Name + "";
                if(i< inputs.selecteddiagnosis.length-1) diagnosis += ",";
            }


            url = "./php/clinicalViewer/appointmentQuery.php?"

            comp = (inputs.comp) ? "&comp=1" : "";
            openn = (inputs.openn) ? "&openn=1" : "";
            canc = (inputs.canc) ? "&canc=1" : "";
            arrived = (inputs.arrived) ? "&arrived=1" : "";
            notArrived = (inputs.notArrived) ? "&notArrived=1" : "";
            opal = (inputs.opal) ? "&opal=1" : "";
            SMS = (inputs.SMS) ? "&SMS=1" : "";
            typeSelect = (inputs.type) ? "&type="+ inputs.type : "";
            specificType = (clinics !="" && inputs.type != 'all') ? "&specificType="+ clinics : "";
            ctypeSelect = (inputs.ctype) ? "&ctype="+ inputs.ctype : "";
            cspecificType = (codes !="" && inputs.ctype != 'all') ? "&cspecificType="+ codes : "";
            dtypeSelect = (inputs.dtype) ? "&dtype="+ inputs.dtype : "";
            dspecificType = (diagnosis !="" && inputs.dtype != 'all') ? "&dspecificType="+ diagnosis : "";

            $http.get(url+"sDate="+sDate+"&eDate="+eDate+"&sTime="+sTime+"&eTime="+eTime+comp+openn+canc+arrived+notArrived+opal+SMS+typeSelect+specificType+ctypeSelect+cspecificType+dtypeSelect+dspecificType+"&clinic="+speciality).then(function (response)
            {
                let info = {};
                info = response.data;
                defer.resolve(info);
            });
            return defer.promise;
        }
    };
});
