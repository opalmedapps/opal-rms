let app = angular.module('vwr',['checklist-model','datatables','datatables.buttons','ui.bootstrap','jlareau.bowser','ngMaterial','ngCookies','ngTable']);

app.controller('main', function($scope,$uibModal,$http,$filter,$mdDialog,$interval,$cookies,$window,DTOptionsBuilder,callScript,bowser)
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

    //=========================================================================
    // Button and page status features
    //=========================================================================
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

    };

    $scope.inputchange = function(){
        $scope.isInputsChange = false;
        $scope.liveState = 'Paused';

    };

    $scope.confidentialMode = function(){
        if($scope.confid == 'Nominal Mode') {
            $scope.login();
        }
        else{
            $scope.confid = 'Nominal Mode';
            sessionStorage.setItem("value",$scope.confid)
            window.clearTimeout($scope.timeout);
        }
    };

    $scope.test = {
        showMenu: 'Show Menu',
        emptyList: false
    };

    $scope.showMenuButton = function(){
        if($scope.test.showMenu == 'Show Menu') $scope.test.showMenu = 'Hide Menu';
        else $scope.test.showMenu = 'Show Menu';
    };

    $scope.OnOffButton = function(){
        if ($scope.inputs.offbutton === 'OFF'){
            $scope.inputs.offbutton = 'ON';
        }
        else $scope.inputs.offbutton = 'OFF';

        $scope.inputchange();
    };


    $scope.qDisableButton = function(){
        if ($scope.inputs.qfilterdisable){
            $scope.inputs.qfilterdisable = false;
        }
        else $scope.inputs.qfilterdisable = true;

        $scope.inputchange();
    };

    $scope.dDisableButton = function(){
        if ($scope.inputs.dfilterdisable){
            $scope.inputs.dfilterdisable= false;
        }
        else $scope.inputs.dfilterdisable = true;

        $scope.inputchange();
    };
    $scope.aDisableButton = function(){
        if ($scope.inputs.afilterdisable){
            $scope.inputs.afilterdisable= false;
        }
        else $scope.inputs.afilterdisable = true;

        $scope.inputchange();
    };

    $scope.AndOrButton = function(){
        if ($scope.inputs.andbutton === 'And'){
            $scope.inputs.andbutton = 'Or';
        }
        else $scope.inputs.andbutton = 'And';
        $scope.inputchange();
    };

    //=========================================================================
    // Reset page setting
    //=========================================================================
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
                qtype: 'all',
                offbutton: 'ON',
                andbutton: 'And',
                afilterdisable: false,
                qfilterdisable: false,
                dfilterdisable: false,
                /*specificType: {name: ''},
                cspecificType: {name: ''},
                dspecificType: {name: ''},*/
                selectedclinics: [],
                selectedcodes: [],
                selecteddiagnosis: [],
                selectedQuestionnaire: [],
                selectedHourNumber: 24,
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

    //=========================================================================
    // Get resource, appointment code and diagnosis list
    //=========================================================================
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

    $http.get("./php/clinicalViewer/getQuestionnaireName.php?").then(function(response)
    {
        $scope.questionnaireType = response.data;
    })

    //=========================================================================
    // Open the Login modal
    //=========================================================================
    $scope.login = function()
    {
        let answer = $mdDialog.confirm(
            {
                templateUrl: './js/vwr/templates/authDialog.htm',
                controller: authDialogController
            })
            .clickOutsideToClose(true);

        $mdDialog.show(answer).then( _ => {
            $scope.confid = 'Confidential Mode';
            sessionStorage.setItem("value",$scope.confid)
            $scope.confidentialTimer();
        });
    };

    //=========================================================================
    // Initialize the page and query the appointments
    //=========================================================================
    $scope.runScript = function (firstTime)
    {
        if(firstTime) {
            $scope.reset();
            $scope.zoomLink = "";
            $scope.showLM = true;
            if(sessionStorage.getItem("value")) {
                $scope.confid = sessionStorage.getItem("value");
            }
            else
            $scope.confid = 'Confidential Mode';
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
        if($scope.inputs.qtype == 'all')
        {
            $scope.inputs.selectedQuestionnaire = [];
        }

        $scope.qdate = new Date();
        $scope.qdate.setSeconds(0,0)

        var nhour = $scope.inputs.selectedHourNumber;
        var nday = 0;
        var nmonth = 0;
        if($scope.inputs.selectedHourNumber>= 24){
            nday = Math.floor(nhour/24);
            nhour = nhour - 24*nday;
        }
        if($scope.qdate.getMonth()==2 && nday >= 28){
            nmonth += 1;
            nday -= 28;
        }
        if(nday >= 30 ){
            nmonth = Math.floor(nday/30);
            nday = nday- 30*nmonth;
        }
        $scope.qdate.setHours($scope.qdate.getHours()- nhour);
        $scope.qdate.setDate($scope.qdate.getDate() - nday );
        $scope.qdate.setMonth($scope.qdate.getMonth() - nmonth);

        $scope.isInputsChange = true;
        $scope.liveState= 'Live'
        $scope.showLM = true;
        if($scope.liveMode === 'Report'){
            $scope.showLM = false;
        }
        $scope.showMenu = 'false';

        if($scope.inputs.dtype === 'specific' && $scope.inputs.selecteddiagnosis.length >0) $scope.diagnosisUsed = true;
        else $scope.diagnosisUsed = false;

        if($scope.inputs.ctype === 'specific' && $scope.inputs.selectedcodes.length >0) $scope.codeUsed = true;
        else $scope.codeUsed = false;

        if($scope.inputs.type === 'specific' && $scope.inputs.selectedclinics.length >0) $scope.clinicsUsed = true;
        else $scope.clinicsUsed = false;

        if($scope.inputs.opal&& $scope.inputs.SMS) $scope.opalUsed = false;
        else $scope.opalUsed = true;

        callScript.getData($scope.convertDate($scope.sDate),$scope.convertDate($scope.eDate),$scope.convertTime($scope.sTime),$scope.convertTime($scope.eTime),$scope.convertDate($scope.qdate),$scope.convertTime($scope.qdate),$scope.inputs,speciality).then(function (response)
        {
            $scope.tableData = response;
            //filter all blood test appointments
            $scope.tableData = $scope.tableData.filter(function(app) {
                return !/blood/.test(app.appName);
            });
            for (data of $scope.tableData){
                data.zoomLinkSent = false;
            }

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
            $scope.confidentialTimer();
        });
    };
    $scope.confidentialTimer = function(){
        document.addEventListener("mousedown", $scope.resetConfidTimer, false);
        document.addEventListener("mousemove", $scope.resetConfidTimer, false);
        document.addEventListener("keypress", $scope.resetConfidTimer, false);
        document.addEventListener("touchmove", $scope.resetConfidTimer, false);
        if($scope.confid === 'Confidential Mode') {
            $scope.timeout = window.setTimeout($scope.confidentialMode, 120000);
        }

    };


    $scope.resetConfidTimer = function(){
        window.clearTimeout($scope.timeout);
        if($scope.confid === 'Confidential Mode') {
            $scope.timeout = window.setTimeout($scope.confidentialMode, 120000);
        }
    };

    //=========================================================================
    // Open the Questionnaire modal
    //=========================================================================
    $scope.openQuestionnaireModal = function (appoint)
    {
        var modalInstance = $uibModal.open(
            {
                animation: true,
                templateUrl: './js/vwr/templates/questionnaireModal.htm',
                controller: questionnaireModalController,
                windowClass: 'questionnaireModal',
                //size: 'lg',
                //backdrop: 'static',
                resolve:
                    {
                        patient: function() {return {'LastName': appoint.lname, 'FirstName': appoint.fname, 'PatientId': appoint.patientId, 'Mrn': appoint.mrn, 'QStatus': appoint.QStatus,'LastQuestionnaireReview':appoint.LastReview,};},
                        //confidMode: function() {return $scope.confid}
                    }
            }).result.then(function(response)
        {

        });
    }

    //=========================================================================
    // Open the Diagnosis modal
    //=========================================================================
    $scope.openDiagnosisModal = function(appoint)
    {
        $uibModal.open({
            animation: true,
            templateUrl: './js/vwr/templates/diagnosisModal.htm',
            controller: diagnosisModalController,
            windowClass: 'diagnosisModal',
            size: 'lg',
            //backdrop: 'static',
            resolve:
                {
                    patient: function() {return {'LastName': appoint.lname, 'FirstName': appoint.fname, 'PatientId': appoint.patientId,};}
                }
        })
    }

    $scope.loadPatientDiagnosis = function(patient)
    {
        $http({
            url: "./php/diagnosis/getPatientDiagnosisList.php",
            method: "GET",
            params: {
                patientId: patient.PatientId
            }
        })
            .then(res => {
                $scope.lastPatientDiagnosisList = res.data
            });
    }

    $scope.loadPatientDiagnosis = function(appoint)
    {
        $http({
            url: "./php/diagnosis/getPatientDiagnosisList.php",
            method: "GET",
            params: {
                patientId: appoint.patientId
            }
        })
        .then(res => {
            $scope.lastPatientDiagnosisList = res.data
        });
    }

    //=========================================================================
    // Open the Selector modal
    //=========================================================================
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
                url: "php/sms/sendSmsForZoom",
                method: "GET",
                params:
                    {
                        patientId: appoint.patientId,
                        zoomLink: $scope.zoomLink,
                        resName: appoint.appName
                    }
            });
            appoint.zoomLinkSent = true;
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

        $scope.qdate = new Date();
        $scope.qdate.setSeconds(0,0)

        var nhour = $scope.inputs.selectedHourNumber;
        var nday = 0;
        var nmonth = 0;
        if($scope.inputs.selectedHourNumber>= 24){
            nday = Math.floor(nhour/24);
            nhour = nhour - 24*nday;
        }
        if($scope.qdate.getMonth()==2 && nday >= 28){
            nmonth += 1;
            nday -= 28;
        }
        if(nday >= 30 ){
            nmonth = Math.floor(nday/30);
            nday = nday- 30*nmonth;
        }
        $scope.qdate.setHours($scope.qdate.getHours()- nhour);
        $scope.qdate.setDate($scope.qdate.getDate() - nday );
        $scope.qdate.setMonth($scope.qdate.getMonth() - nmonth);

        if($scope.isInputsChange && $scope.liveMode === 'Live'){
            callScript.getData($scope.convertDate($scope.sDate), $scope.convertDate($scope.eDate), $scope.convertTime($scope.sTime), $scope.convertTime($scope.eTime),$scope.convertDate($scope.qdate),$scope.convertTime($scope.qdate), $scope.inputs, speciality).then(function (response) {
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
        getData: function(sDate,eDate,sTime,eTime,qdate,qtime,inputs,speciality)
        {
            let defer = $q.defer();
            var clinics = "";
            for (i = 0; i < inputs.selectedclinics.length; i++) {
                clinics += "\"" +inputs.selectedclinics[i].Name + "\"";
                if(clinics.length > 5300)break;
                if(i< inputs.selectedclinics.length-1) clinics += ",";
            }
            var codes = "";
            for (i = 0; i < inputs.selectedcodes.length; i++) {
                codes += "'"+inputs.selectedcodes[i].Name + "'";
                if(i< inputs.selectedcodes.length-1) codes += ",";
            }
            var diagnosis = "";
            for (i = 0; i < inputs.selecteddiagnosis.length; i++) {
                diagnosis += ""+inputs.selecteddiagnosis[i].subcode + "";
                if(i< inputs.selecteddiagnosis.length-1) diagnosis += ",";
            }

            var questionnaireType = "";
            for (i = 0; i < inputs.selectedQuestionnaire.length; i++) {
                questionnaireType += ""+inputs.selectedQuestionnaire[i].Name + "";
                if(i< inputs.selectedQuestionnaire.length-1) questionnaireType += ",";
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
            qtypeSelect = (inputs.qtype) ? "&qtype="+ inputs.qtype : "";
            qspecificType = (questionnaireType !="" && inputs.qtype != 'all')? "&qspecificType="+ questionnaireType :"";
            selectedDate = "&qselectedDate="+qdate + "&qselectedTime="+qtime;
            offb = (inputs.offbutton) ? "&offbutton="+ inputs.offbutton : "";
            andb = (inputs.andbutton) ? "&andbutton="+ inputs.andbutton : "";
            afilter = (inputs.afilterdisable) ? "&afilter=1": "";
            qfilter = (inputs.qfilterdisable) ? "&qfilter=1": "";

            if(inputs.dfilterdisable){
                dtypeSelect = "&dtype=all";
                opal = "&opal=2";
                SMS = "&SMS=2";
            }
            if(inputs.qfilterdisable){
                qtypeSelect = "&qtype=all";
                offb = "&offbutton=OFF";
                andb = "&andbutton=Add";
            }


            $http.get(url+"sDate="+sDate+"&eDate="+eDate+"&sTime="+sTime+"&eTime="+eTime+comp+openn+canc+arrived+
                notArrived+opal+SMS+typeSelect+specificType+ctypeSelect+cspecificType+dtypeSelect+dspecificType +
                qtypeSelect+qspecificType+selectedDate+offb+andb+afilter+qfilter+"&clinic="+speciality).then(function (response){
                let info = {};
                info = response.data;
                defer.resolve(info);
            });
            return defer.promise;
        }
    };
});
