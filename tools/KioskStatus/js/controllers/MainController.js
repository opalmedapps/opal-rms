app.controller('MainController', function($scope, KioskInfo, $interval) {

    $scope.messageCheckboxes = ['Heartbeat', 'Successful Check-In', 'Unsuccessful Check-In', 'All'];
    $scope.tableHeaders = ['Kiosk ID', 'No. Successful Check-In', 'No. Unsuccessful Check-In',
        'No. Heartbeat', 'Avg. Heartbeat Rate', 'Std. of Heartbeat Rate', 'Med. Heartbeat Rate', 'Min. Heartbeat Rate'
    , 'Max. Heartbeat Rate'];
    $scope.messages = KioskInfo.getMessages();
    $scope.kiosk = KioskInfo.getKiosks();
    $scope.start = 'yyyy/MM/dd';
    $scope.end = 'yyyy/MM/dd';
    $scope.datePrompt = "Unknown";
    $scope.leftEnd = 0;
    $scope.rightEnd = 0;
    $scope.startDateInMilliSeconds = 0;
    $scope.endDateInMilliSeconds = 0;
    $scope.historyFlag = true;
    $scope.currentlyDead = {};

    //This function is used to view/hide visual elements when a certain option in the menu is clicked
    //For example, if you click hourly heartbeat chart option, the web page only shows that chart but everything else is hidden
    $scope.toggleChart = function (elemID) {
        var someArray = ['heartBeatChart', 'checkInAttemptsBarChart', 'mainChart', 'tableOfSummary', 
        'checkInChart', 'unsuccessfulCheckIn'];

        someArray.forEach(function (elem) {

            var x = document.getElementById(elem);

            if (elem === elemID) {
                if (elem === "tableOfSummary") {
                    x.style.display = "table"
                } else {
                    x.style.display = "block";
                }
            } else {
                x.style.display = "none";
            }
        });

    };

    //return the index in kiosk array
    $scope.findKioskInArray = function (name) {
        var indexInArray = -1;

        if (typeof $scope.kiosk != 'undefined') {
            $scope.kiosk.forEach(function (obj, index) {
                if (obj.ID === name) {
                    indexInArray = index;
                }
            });
        }

        return indexInArray;
    };

    //redraw charts
    $scope.redrawAllCharts = function () {
        if (typeof $scope.myChart != 'undefined') $scope.myChart.redraw();
        if (typeof $scope.hourlyCheckInChart != 'undefined') $scope.hourlyCheckInChart.redraw();
        if (typeof $scope.hourlyUnsuccessfulCheckInChart != 'undefined') $scope.hourlyUnsuccessfulCheckInChart.redraw();
        if (typeof $scope.hourlyChart != 'undefined') $scope.hourlyChart.redraw();
        if (typeof $scope.summaryChart != 'undefined') $scope.summaryChart.redraw();      
    };

    //this method is triggered when a user specifies date range on the calendar
    //the date range is binded to scope.datePrompt
    $scope.processDate = function() {

        var separateDates = $scope.datePrompt.split("-");
        var startDateArray = separateDates[0].split(" ").slice(0, -1);
        var endDateArray = separateDates[1].split(" ");
        $scope.historyFlag = true;

        endDateArray.shift();

        var parseDate = function(dateArray) {

            var [dayMonth, hourMin, ampm] = dateArray;
            var [month, day] = dayMonth.split("/");
            var [hour, min] = hourMin.split(":");
            if (ampm == 'PM') hour = parseInt(hour) + 12;

            return {
                year: (new Date()).getFullYear(),
                month: parseInt(month),
                day: parseInt(day),
                hour: parseInt(hour),
                min: parseInt(min),
                toString: function() {
                    return this.year + '/' + this.month + '/' + this.day + ' '
                    + this.hour + ':' + this.min;
                }
            };

        };

        //parse dates
        var startDateInfo = parseDate(startDateArray);
        var endDateInfo = parseDate(endDateArray);
        var startDateObj = new Date(startDateInfo.year, startDateInfo.month - 1, startDateInfo.day, 
            startDateInfo.hour, startDateInfo.min, 0);
        var endDateObj = new Date(endDateInfo.year, endDateInfo.month - 1, endDateInfo.day,
            endDateInfo.hour, endDateInfo.min, 0);
        var dateRangeString = startDateInfo.toString() + " - " + endDateInfo.toString();
        
        //start date and end date into milliseconds
        $scope.startDateInMilliSeconds = Date.UTC(startDateInfo.year, startDateInfo.month - 1, startDateInfo.day, startDateInfo.hour, startDateInfo.min);
        $scope.endDateInMilliSeconds = Date.UTC(endDateInfo.year, endDateInfo.month - 1, endDateInfo.day, endDateInfo.hour, endDateInfo.min);

        //update prompt
        $scope.datePrompt = dateRangeString;

        if (typeof $scope.myChart != 'undefined') {
            $scope.process(startDateObj, endDateObj);
            $scope.redrawAllCharts();
        } else {
            $scope.process(startDateObj, endDateObj);
        }
    };

    //find currently dead kiosks
    $scope.findCurrentlyDead = function (lastRefreshedTime) {

        for (var i = 0; i < $scope.kiosk.length; i++) {
            var kioskID = $scope.kiosk[i].ID;
            var avg = $scope.kiosk[i].averageHeartBeatInterval;
            var std = $scope.kiosk[i].standardDeviation;
            var interval = 2 * (avg + std);

            $scope.currentlyDead[kioskID] = { 
                data: [],
                middle: [] 
            };

            if (kioskID.indexOf('Reception') !== -1) continue;

            if ($scope.lastPoint[kioskID].data.length !== 0) {
                if (interval === 0) continue;

                var [x, y] = $scope.lastPoint[kioskID].data[0];
                var diff = (lastRefreshedTime - x) / 60000;

                if (interval < diff) {
                    $scope.currentlyDead[kioskID].data = [[x, y], [lastRefreshedTime, y]];
                    $scope.currentlyDead[kioskID].middle = [[(x + lastRefreshedTime) / 2, y]]
                }
            } else {
                var y = KioskInfo.getHashTable()[kioskID];

                $scope.currentlyDead[kioskID].data = [[$scope.startDateInMilliSeconds, y], [lastRefreshedTime, y]];
                $scope.currentlyDead[kioskID].middle = [[($scope.startDateInMilliSeconds + lastRefreshedTime) / 2, y]];
            }
        }
    };

    //angular service used to refresh highcharts in real-time every specified millliseconds
    $interval(function () {
        if ($scope.historyFlag === false) {
            $scope.launchRealTime();
            $scope.redrawAllCharts();
        }
    }, 120000);

    //the method is triggered when real time button is clicked
    $scope.launchRealTime = function () {
        var today = new Date();
        var year = today.getFullYear();
        var month = today.getMonth();
        var day = today.getDate();

        $scope.historyFlag = false;
        $scope.startDateInMilliSeconds = Date.UTC(year, month, day);
        $scope.endDateInMilliSeconds = $scope.startDateInMilliSeconds + 86400000;
        $scope.process(today, today);
    };

    //helper method
    $scope.computeStandardDeviation = function (values) {

        var avg = $scope.computeAverage(values);

        var squareDiffs = values.map(function(value){
            var diff = value - avg;
            var sqrDiff = diff * diff;
            return sqrDiff;
        });

        var avgSquareDiff = $scope.computeAverage(squareDiffs);

        var stdDev = Math.sqrt(avgSquareDiff);

        return stdDev;
    };

    //helper method
    $scope.computeAverage = function (data) {

        var sum = data.reduce(function(sum, value){
            return sum + value;
        }, 0);

        var avg = sum / data.length;

        return avg;
    };


    //the method loads irregular heartbeat with given kiosk ID
    $scope.loadIrregularData = function (ID) {

        var index = $scope.findKioskInArray(ID);

        if (typeof $scope.checkInTimesInfo[ID] === 'undefined' || index === -1) return;

        var average = $scope.kiosk[index].averageHeartBeatInterval; // (in minutes)
        var std = $scope.kiosk[index].standardDeviation; // (in minutes)
        var leftEnd = average - (3/2 * std);
        var distance = 0;
        var heartBeatArray = $scope.checkInTimesInfo[ID].heartbeat;
        var irregularDataArray = [];
        var kioskHashTable = KioskInfo.getHashTable();
        var yValue = kioskHashTable[ID];

        if (leftEnd <= 0 || heartBeatArray.length < 1) {
            $scope.checkInTimesInfo[ID].irregularHeartbeat = [];
            $scope.kiosk[index].irregular = 0;
            return;
        }

        var currentPoint = heartBeatArray[0][0];
        var previousPoint = heartBeatArray[0][0];

        for (var i = 1; i < heartBeatArray.length; i++) {
            currentPoint = heartBeatArray[i][0];
            distance = (currentPoint - previousPoint) / 60000;
            if (distance < leftEnd ) irregularDataArray.push([currentPoint, yValue]);
            previousPoint = currentPoint;
        }

        $scope.checkInTimesInfo[ID].irregularHeartbeat = irregularDataArray;
        $scope.kiosk[index].irregular = irregularDataArray.length;
    };

    //reset kiosk information
    $scope.resetSummary = function (kioskID) {

        var index = $scope.findKioskInArray(kioskID);

        if (index === -1) return;

        $scope.kiosk[index].success = 0;
        $scope.kiosk[index].unsuccess = 0;
        $scope.kiosk[index].heartbeat = 0;
        $scope.kiosk[index].averageHeartBeatInterval = 0;
        $scope.kiosk[index].standardDeviation = 0;
        $scope.kiosk[index].maximumInterval = 0;
        $scope.kiosk[index].minimumInterval = 0;
        $scope.kiosk[index].medianInterval = 0;
        $scope.kiosk[index].irregular = 0;

    };

    //update kiosk information given start and end date (in milliseconds) and the ID
    $scope.updateSummary = function (ID, start, end) {

        var distanceSum = 0;
        var currentPoint = 0;
        var heartBeatArray = [];
        var distance = 0;
        var distanceArray = [];
        var previousPoint = 0;
        var index = $scope.findKioskInArray(ID);
        var successCnt = 0;
        var unSuccessCnt = 0;
        var heartBeatProperty = KioskInfo.getHeartBeatProperty();
        var successfulCheckInProperty = KioskInfo.getSuccessfulCheckInProperty();
        var unsuccessfulCheckInProperty = KioskInfo.getUnsuccessfulCheckInProperty();
        var property = [heartBeatProperty, successfulCheckInProperty, unsuccessfulCheckInProperty];

        if (typeof $scope.checkInTimesInfo[ID] === 'undefined') return;
        if (end < start) return;
        if (end < 0 || start < 0) return;
        if (index === -1) return;
        if (start < $scope.startDateInMilliSeconds || end > $scope.endDateInMilliSeconds) {
            $scope.resetSummary(ID);
            return;
        }

        for (var i = 0; i < property.length; i++) {
            for (var j = 0; j < $scope.checkInTimesInfo[ID][property[i]].length; j++) {
                currentPoint = $scope.checkInTimesInfo[ID][property[i]][j][0];

                if (currentPoint < start || currentPoint > end) continue;
                if (property[i] === heartBeatProperty) heartBeatArray.push(currentPoint);
                if (property[i] === successfulCheckInProperty) successCnt++;
                if (property[i] === unsuccessfulCheckInProperty) unSuccessCnt++;
            }
        }

        if (heartBeatArray.length == 0) {
            $scope.resetSummary(ID);
            return;
        }

        currentPoint = heartBeatArray[0];
        previousPoint = heartBeatArray[0];

        for (var i = 1; i < heartBeatArray.length; i++) {
            currentPoint = heartBeatArray[i];
            distance = currentPoint - previousPoint;
            distanceSum += distance;
            distanceArray.push(distance);
            previousPoint = currentPoint;
        }

        distanceArray.sort((a, b) => a - b);

        var lowMiddle = Math.floor((distanceArray.length - 1) / 2);
        var highMiddle = Math.ceil((distanceArray.length - 1) / 2);
        var median = (distanceArray[lowMiddle] + distanceArray[highMiddle]) / 2;

        $scope.kiosk[index].success = successCnt;
        $scope.kiosk[index].unsuccess = unSuccessCnt;
        $scope.kiosk[index].heartbeat = heartBeatArray.length;

        if (distanceArray.length > 0) {
            $scope.kiosk[index].averageHeartBeatInterval = $scope.computeAverage(distanceArray) / 60000;
            $scope.kiosk[index].standardDeviation = $scope.computeStandardDeviation(distanceArray) / 60000;
            $scope.kiosk[index].maximumInterval = distanceArray[distanceArray.length - 1] / 60000;
            $scope.kiosk[index].minimumInterval = distanceArray[0] / 60000;
            $scope.kiosk[index].medianInterval = median / 60000;
        }

    };


    //compute number of messages in a periodic manner
    //and load the data into the object storage
    //For example, if interval is one hour, it computes hourly trend of messages
    $scope.loadTrendData = function(interval, storage) { 

        var today = new Date();
        var nowInMilliSeconds = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate(), today.getHours(), today.getMinutes());

        for (var property in $scope.checkInTimesInfo) {

            if (storage.hasOwnProperty(property)) {
                if (typeof $scope.checkInTimesInfo[property] === 'undefined') continue;

                for (var message in $scope.checkInTimesInfo[property]) {
                    if (message === 'irregularHeartbeat') continue;

                    var numIterations = $scope.checkInTimesInfo[property][message].length;

                    if (numIterations < 1) continue;

                    var cnt = 0;
                    var readUntil = $scope.startDateInMilliSeconds;
                    var point = 0;

                    for (var index = 0; index < numIterations; index++) {
                        point = $scope.checkInTimesInfo[property][message][index][0];
                        if (point <= readUntil) {
                            cnt += 1;
                        } else {
                            storage[property][message].push([readUntil, cnt]);
                            readUntil += interval;
                            while(readUntil < point) {
                                storage[property][message].push([readUntil, 0]);
                                readUntil += interval;
                            }
                            cnt = 1;
                        }
                    }

                    storage[property][message].push([readUntil, cnt]);
                    readUntil += interval;

                    while (readUntil <= $scope.endDateInMilliSeconds && readUntil < nowInMilliSeconds) {
                        storage[property][message].push([readUntil, 0]);
                        readUntil += interval;
                    }
                }
            }
        }
    };

    //This method loads hourly trend
    $scope.loadHourlyData = function () {

        var hoursInMilliSeconds = 3600000;
        $scope.hourlyData = {};

        $scope.kiosk.forEach( function (element) {
            $scope.hourlyData[element.ID] = {
                heartbeat: [],
                success: [],
                unsuccess: []
            };
        });

        $scope.loadTrendData(hoursInMilliSeconds, $scope.hourlyData);
    };

    //This method loads daily trend
    $scope.loadDailyData = function () {

        var dayInMilliSeconds = 86400000;
        $scope.dailyData = {};

        $scope.kiosk.forEach( function (element) {
            $scope.dailyData[element.ID] = {
                heartbeat: [],
                success: [[$scope.startDateInMilliSeconds, 4],[$scope.startDateInMilliSeconds + dayInMilliSeconds, 10]],
                unsuccess: []
            };
        });
    };

    //This method loads dead kiosks
    $scope.loadInactiveData = function() {

        var kioskHashTable = KioskInfo.getHashTable();
        $scope.inactiveData = {};

        $scope.kiosk.forEach(function(element) {
           $scope.inactiveData[element.ID] = {
               dead: [],
               middle: []
           }
        });

        for(var property in $scope.inactiveData) {
            if ($scope.inactiveData.hasOwnProperty(property)) {

                if (property.indexOf('Reception') !== -1) continue;

                var yValue = kioskHashTable[property];
                var index = $scope.findKioskInArray(property);
                var avg = $scope.kiosk[index].averageHeartBeatInterval * 60000;
                var std = $scope.kiosk[index].standardDeviation * 60000;
                var heartBeatProperty = KioskInfo.getHeartBeatProperty();
                var interval = 2 * (avg + std);

                if (typeof $scope.checkInTimesInfo[property] === 'undefined') continue;
                if ($scope.checkInTimesInfo[property][heartBeatProperty].length === 0) continue;

                var heartBeatArray = $scope.checkInTimesInfo[property][heartBeatProperty];
                var prevTime = heartBeatArray[0];

                for (var i = 1; i < heartBeatArray.length; i++) {

                    var currTime = heartBeatArray[i][0];
                    var timeDifference = currTime - prevTime;

                    if (timeDifference > interval) {
                        $scope.inactiveData[property].dead.push([prevTime, yValue]);
                        $scope.inactiveData[property].dead.push([currTime, yValue]);
                        $scope.inactiveData[property].dead.push([currTime, null]);

                        var middlePoint = Math.floor((currTime + prevTime)) / 2;

                        $scope.inactiveData[property].middle.push([middlePoint, yValue]);
                    }
                    prevTime = currTime;
                }
            }
        }
    };

    //This method is used to insert a point (x,y) into scope.checkInTimes[kioskID].message
    //The last inserted point is stored in scope.lastPoint[kioskID]
    //This method 'pipes' the last inserted point into checkInTimes[kioskID].message if a new point is going to be inserted
    $scope.insertPoint = function (kioskID, kioskMessage, xPoint, yPoint) {

        var message = '', x = '', y = '';

        if ($scope.historyFlag === true) {
            if (xPoint < $scope.startDateInMilliSeconds || xPoint > $scope.endDateInMilliSeconds){
                return;
            }
        }

        if ($scope.findKioskInArray(kioskID) === -1) {
            return;
        }

        if ($scope.lastPoint[kioskID].data.length !== 0) {
            message = $scope.lastPoint[kioskID].message;
            [x, y] = $scope.lastPoint[kioskID].data[0];
            $scope.checkInTimesInfo[kioskID][message].push([x, y]);
        }

        $scope.lastPoint[kioskID].data = [[xPoint, yPoint]];
        $scope.lastPoint[kioskID].message = kioskMessage;

    };

    //compute last three hours in milliseconds respect to the current time
    $scope.computeLastThreeHours = function () {

        var lastPoints = [];
        var threeHoursInMilliSeconds = 10800000;

        $scope.kiosk.forEach( function (kioskObj) {
            if ($scope.lastPoint[kioskObj.ID].data.length != 0) {
                lastPoints.push($scope.lastPoint[kioskObj.ID].data[0][0]);
            }
        });

        if (lastPoints.length != 0) {
            var latestPoint = Math.max.apply(null, lastPoints);
            var leftEnd = latestPoint - threeHoursInMilliSeconds + 1800000;
            $scope.leftEnd = leftEnd;
            $scope.rightEnd = latestPoint;
        }
    };


    //convert date object into a name of corresponding log file
    $scope.formatLogFile = function (dateObj) {
        var month = dateObj.getMonth() + 1;
        var day = dateObj.getDate();
        var year = dateObj.getFullYear();

        return 'logfile_' + month + day + year + '.html';
    };

    //date formatter
    $scope.dateFormatter = function (dateObj) {
        var month = dateObj.getMonth() + 1;
        var day = dateObj.getDate();
        var year = dateObj.getFullYear();
        var hour = dateObj.getHours();
        var min = dateObj.getMinutes();

        if (hour < 10) hour = '0' + hour;
        if (min < 10) min = '0' + min;

        return month + '/' + day + '/' + year + ' ' + hour + ':' + min;
    };

    //Get start date and end date as date objects
    //Retrieve log files using the given date range
    //load and update kiosk information and highcharts
    $scope.process = function (startDateObj, endDateObj) {

        var files = [];
        var heartBeatProperty = KioskInfo.getHeartBeatProperty();
        var successfulCheckInProperty = KioskInfo.getSuccessfulCheckInProperty();
        var unsuccessfulCheckInProperty = KioskInfo.getUnsuccessfulCheckInProperty();
        var filepath = '../logs/kiosk/';
        var startDateString = $scope.dateFormatter(startDateObj);
        var endDateString = $scope.dateFormatter(endDateObj);
        var dateRangeString = startDateString + ' - ' + endDateString;

        $scope.datePrompt = dateRangeString;

        //get all the log file names
        if (endDateObj < startDateObj) return;
        if (startDateObj === endDateObj) {
            files.push(filepath + $scope.formatLogFile(startDateObj));
        } else {
            while ( startDateObj <= endDateObj) {
                files.push(filepath + $scope.formatLogFile(startDateObj));
                startDateObj = new Date(startDateObj.setTime(startDateObj.getTime() + 86400000));
            }
        }

        var kioskValueTable = KioskInfo.getHashTable();
        $scope.checkInTimesInfo = {};
        $scope.lastPoint = {};

        $scope.kiosk.forEach( function (kioskObj) {
            var id = kioskObj.ID;
            $scope.checkInTimesInfo[id] = {};
            $scope.lastPoint[id] = {};
            $scope.checkInTimesInfo[id][heartBeatProperty] = [];
            $scope.checkInTimesInfo[id][successfulCheckInProperty] = [];
            $scope.checkInTimesInfo[id][unsuccessfulCheckInProperty] = [];
            $scope.checkInTimesInfo[id]['irregularHeartbeat'] = [];
            $scope.lastPoint[id]['data'] = [];
            $scope.lastPoint[id]['message'] = '';
        });

        function parseDate (fullYear, time) {
            var tokenizeMonthDayYear = fullYear.split('/');
            var month = parseInt(tokenizeMonthDayYear[0]);
            var day = parseInt(tokenizeMonthDayYear[1]);
            var year = parseInt(tokenizeMonthDayYear[2]);

            var tokenizeHourMinuteSecond = time.split(':');
            var hours = parseInt(tokenizeHourMinuteSecond[0]);
            var minutes = parseInt(tokenizeHourMinuteSecond[1]);
            var seconds = parseInt(tokenizeHourMinuteSecond[2]);

            return Date.UTC(year, month - 1, day, hours, minutes, seconds);
        };

        function replaceAll(str, find, replace) {
            return str.replace(new RegExp(find, 'g'), replace);
        };

        for (var i = 0; i < files.length; i++) {
            var rawFile = new XMLHttpRequest();
            var allText = '';

            rawFile.open("GET", files[i], false);
            rawFile.onreadystatechange = function () {
                if (rawFile.readyState === 4) {
                    if (rawFile.status === 200 || rawFile.status == 0) {
                        allText = rawFile.responseText;

                        if (typeof allText == '') {
                            alert('Failed to load the file');
                        }

                        var lines = allText.split(/\r?\n/g).filter(a => a !== '');
                        var linesLength = lines.length;
                        var data = [];

                        for (var i = 0; i < linesLength; i++) {
                            var words = lines[i].split(" ").filter(a => a !== "").filter(a => a !== ",").map(a => a.replace(",", "")).map(a => a.replace("###", ""));
                            data.push(words);
                        }
                
                        //categorize messages
                        for (var i = 0; i < data.length; i++) {
                            var line = replaceAll(data[i].toString(), ',', ' ');

                            if (line.indexOf("Please scan your medicare card to check in") >= 0) {
                                if (data[i].includes("default")) {
                                    $scope.insertPoint(data[i][4], heartBeatProperty, parseDate(data[i][0], data[i][1]), kioskValueTable[data[i][4]]);
                                } else if (data[i].includes("RAMQ")) {
                                    $scope.insertPoint(data[i][3], heartBeatProperty, parseDate(data[i][0], data[i][1]), kioskValueTable[data[i][3]]);
                                }
                            } else if (line.indexOf("waiting") >= 0 || line.indexOf("have your photo taken") >= 0 || line.indexOf("to complete the check-in process") >= 0) {
                                $scope.insertPoint(data[i][3], successfulCheckInProperty, parseDate(data[i][0], data[i][1]), kioskValueTable[data[i][3]]);
                            } else if (line.indexOf("Unable") >= 0) {
                                $scope.insertPoint(data[i][3], unsuccessfulCheckInProperty, parseDate(data[i][0], data[i][1]), kioskValueTable[data[i][3]]);
                            }
                        }
                    }
                }
            }
            rawFile.send(null);
        }

        //update summary
        $scope.kiosk.forEach( function (element) {
            $scope.updateSummary(element.ID, $scope.startDateInMilliSeconds, $scope.endDateInMilliSeconds);
            $scope.loadIrregularData(element.ID);
        });

        //load data
        $scope.loadHourlyData();
        $scope.loadInactiveData();

        //find currently dead kiosks
        if ($scope.historyFlag === false) {
            var today = new Date();
            var nowInMilliSeconds = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate(), today.getHours(), today.getMinutes());
            $scope.findCurrentlyDead(nowInMilliSeconds);
        }

        //create highcharts
        $scope.createScatterChart();
        $scope.createColumnSummaryChart();
        $scope.createHourlyHeartbeatTrendChart();
        $scope.createHourlyCheckInChart();
        $scope.createHourlyUnsuccessfulCheckInChart();
        $scope.computeLastThreeHours();

    };

    //main scatter chart
    $scope.createScatterChart = function () {

        var series = [];
        var kioskMessages = KioskInfo.getMessages();

        $scope.kiosk.forEach(function (element) {
            var message = $scope.lastPoint[element.ID].message;
            var color = '';
            var heartBeatProperty = KioskInfo.getHeartBeatProperty();
            var successfulCheckInProperty = KioskInfo.getSuccessfulCheckInProperty();
            var unsuccessfulCheckInProperty = KioskInfo.getUnsuccessfulCheckInProperty();

            switch(message) {
                case heartBeatProperty:
                    color = KioskInfo.getHeartBeatColor();
                    break;
                case successfulCheckInProperty:
                    color = KioskInfo.getSuccessfulCheckInColor();
                    break;
                case unsuccessfulCheckInProperty:
                    color = KioskInfo.getUnsuccessfulCheckInColor();
            }

            series.push({
                name: message,
                fontFamily: 'Arial',
                color: color,
                data: $scope.lastPoint[element.ID].data,
                showInLegend: false,
                marker: {
                    symbol: KioskInfo.getMessageSymbol($scope.lastPoint[element.ID].message),
                    radius: 12.5
                },
                dataLabels: {
                    enabled: true,
                    formatter: function () {
                        return $scope.lastPoint[element.ID].message
                    },
                    color: 'black'
                }
            });
        });

        $scope.kiosk.forEach(function (element) {
            series.push({
                name: element.ID + "_" + KioskInfo.getHeartBeatProperty(),
                fontFamily: 'Arial',
                color: KioskInfo.getHeartBeatColor(),
                data: $scope.checkInTimesInfo[element.ID].heartbeat,
                showInLegend: false,
                marker: {
                    symbol: kioskMessages[0].symbol
                }
            });
        });

        $scope.kiosk.forEach(function (element) {
            series.push({
                name: element.ID + "_" + KioskInfo.getSuccessfulCheckInProperty(),
                fontFamily: 'Arial',
                color: KioskInfo.getSuccessfulCheckInColor(),
                data: $scope.checkInTimesInfo[element.ID].success,
                showInLegend: false,
                marker: {
                    symbol: kioskMessages[1].symbol
                }
            });
        });

        $scope.kiosk.forEach(function (element) {
            series.push({
                name: element.ID + "_" + KioskInfo.getUnsuccessfulCheckInProperty(),
                fontFamily: 'Arial',
                color: KioskInfo.getUnsuccessfulCheckInColor(),
                data: $scope.checkInTimesInfo[element.ID].unsuccess,
                showInLegend: false,
                marker: {
                    symbol: kioskMessages[2].symbol
                }
            });
        });

        series.push({
            name: 'Irregular_Heartbeat',
            fontFamily: 'Arial',
            color: 'rgb(50,205,50)',
            data: [],
            marker: {
                symbol: kioskMessages[0].symbol
            }
        });

        series.push({
            name: KioskInfo.getMessages()[0].message,
            fontFamily: 'Arial',
            color: KioskInfo.getMessages()[0].color,
            data: [],
            marker: {
                symbol: kioskMessages[0].symbol
            }
        });

        series.push({
            name: KioskInfo.getMessages()[1].message,
            fontFamily: 'Arial',
            color: KioskInfo.getMessages()[1].color,
            data: [],
            marker: {
                symbol: kioskMessages[1].symbol
            }
        });

        series.push({
            name: KioskInfo.getMessages()[2].message,
            fontFamily: 'Arial',
            color: KioskInfo.getMessages()[2].color,
            data: [],
            marker: {
                symbol: kioskMessages[2].symbol
            }
        });

        series.push({
            name: 'dead',
            fontFamily: 'Arial',
            color: 'rgb(178,34,34)',
            data: [],
            type: 'line'
        });

        //load dead
        $scope.kiosk.forEach( function (kioskObj) {
            if ($scope.inactiveData[kioskObj.ID].dead.length !== 0) {
                series.push({
                    type: 'line',
                    data: $scope.inactiveData[kioskObj.ID].dead,
                    fontFamily: 'Arial',
                    visible: true,
                    lineWidth: 5,
                    showInLegend: false,
                    color: '#B22222',
                    marker: {
                        enabled: false
                    },
                    enableMouseTracking: false
                });
                series.push({
                    data: $scope.inactiveData[kioskObj.ID].middle,
                    fontFamily: 'Arial',
                    showInLegend: false,
                    dataLabels: {
                        enabled: true,
                        formatter: function () {
                            return 'dead'
                        },
                        color: '#B22222'
                    },
                    marker: {
                        radius: 0
                    },
                });
            }
        });

        
        if ($scope.historyFlag === false) {
            $scope.kiosk.forEach( function (kioskObj) {
                if ($scope.currentlyDead[kioskObj.ID].data.length !== 0) {
                    series.push({
                        type: 'line',
                        data: $scope.currentlyDead[kioskObj.ID].data,
                        fontFamily: 'Arial',
                        visible: true,
                        lineWidth: 5,
                        showInLegend: false,
                        color: '#B22222',
                        marker: {
                            enabled: false
                        },
                        enableMouseTracking: false
                    });
                    series.push({
                        data: $scope.currentlyDead[kioskObj.ID].middle,
                        fontFamily: 'Arial',
                        showInLegend: false,
                        dataLabels: {
                            enabled: true,
                            formatter: function () {
                                return 'dead'
                            },
                            color: '#B22222'
                        },
                        marker: {
                            radius: 0
                        },
                    });
                }
            });            
        }

        //load irregularData
        $scope.kiosk.forEach (function (kioskObj) {

            if ($scope.checkInTimesInfo[kioskObj.ID].irregularHeartbeat.length !== 0) {
                series.push({
                    name: kioskObj.ID + '_' + 'Irregular_Heartbeat',
                    fontFamily: 'Arial',
                    color: 'rgb(50,205,50)',
                    data: $scope.checkInTimesInfo[kioskObj.ID].irregularHeartbeat,
                    showInLegend: false,
                    marker: {
                        symbol: kioskMessages[0].symbol
                    }
                });
            }

        });

        var fromRightEnd;
        if ($scope.historyFlag === false) fromRightEnd = 10800000 - 1800000;

        $scope.myChart = Highcharts.chart('container', {
            chart: {
                type: 'scatter',
                height: (7.9 / 16 * 100) + '%',
                zoomType: 'x',
                resetZoomButton: {
                    position: {
                        align: 'left', // right by default
                        verticalAlign: 'top',
                        x: 10,
                        y: 10
                    },
                    relativeTo: 'chart',
                    theme: {
                        display: 'none'
                    }
                }
            },
            title: {
                text: '<b>Kiosk Activity</b>',
                fontFamily: 'Arial',
                margin: 20,
                style: {
                    fontSize: "large"
                }
            },
            subtitle: {
                fontFamily: 'Arial'
            },

            xAxis: {
                type: 'datetime',
                title: {
                    enabled: true,
                    text: 'Time',
                    fontFamily: 'Arial'
                },
                labels: {
                    format: '{value:%H:%M:%S}'
                },
                startOnTick: true,
                endOnTick: true,
                showLastLabel: true,
                tickInterval: 1800000,
                range: fromRightEnd
            },

            yAxis: {
                min: 1,
                max: 11,
                tickInterval: 1,
                title: {
                    text: '<b>Kiosk ID</b>',
                    fontFamily: 'Arial'
                },
                labels: {
                    formatter: function() {

                        var kioskTable = KioskInfo.getHashTable();

                        for(var key in kioskTable) {

                            if (kioskTable.hasOwnProperty(key)) {
                                if (this.value === kioskTable[key]) {
                                    return "<b>" + key + "</b>";
                                }
                            }
                        }
                    }
                }
            },


            legend: {
                enabled: true,
                symbolHeight: 20,
                itemStyle: {
                    fontSize:'medium'
                }
            },

            plotOptions: {
                scatter: {
                    marker: {
                        radius: 5,
                        states: {
                            hover: {
                                enabled: true,
                                lineColor: 'rgb(100,100,100)'
                            }
                        }
                    },
                    states: {
                        hover: {
                            marker: {
                                enabled: false
                            }
                        }
                    },
                    tooltip: {
                        headerFormat: '<b>{series.name}</b><br>',
                        pointFormat: 'Date: {point.x:%b %d, %Y} <br> Time: {point.x:%H:%M:%S} '

                    },
                    events: {
                        legendItemClick: function () {

                            var message = this.name;

                            var property = KioskInfo.getCorrespondingMessageProperty(message);

                            for (var i = 0; i < $scope.myChart.series.length; i++) {

                                if (typeof property === 'undefined') {

                                    if ($scope.myChart.series[i].name.indexOf('_Irregular_Heartbeat') !== -1) {
                                        $scope.myChart.series[i].update({
                                            visible: $scope.myChart.series[i].visible ? false : true
                                        });
                                    }

                                } else {

                                    if ($scope.myChart.series[i].name.indexOf('_' + property) !== -1) {
                                        $scope.myChart.series[i].update({
                                            visible: $scope.myChart.series[i].visible ? false : true
                                        });
                                    }

                                }
                            }
                        }
                    }
                }
            },
            series: series
        });
    };

    //horizontal column chart (ratio)
    $scope.createColumnSummaryChart = function() {

        var categories = [];
        var checkInValues = [];
        var failedValues = [];

        $scope.kiosk.forEach(function (kioskObj) {
           categories.push(kioskObj.ID);
           checkInValues.push(kioskObj.success);
           failedValues.push(kioskObj.unsuccess);
        });

        $scope.summaryChart = Highcharts.chart('bar-container', {
            chart: {
                type: 'bar',
                height: (7.9 / 16 * 100) + '%'
            },
            title: {
                text: 'Check-In Attempts'
            },
            xAxis: {
                categories: categories
            },
            yAxis: {
                min: 0,
                title: {
                    text: 'Check-In Attempts'
                }
            },
            legend: {
                reversed: true
            },
            plotOptions: {
                series: {
                    stacking: 'normal'
                }
            },
            series: [
                {
                    name: 'Successful Check-In',
                    data: checkInValues,
                    color: 'rgb(100,149,237)'
                }, {
                    name: 'Unsuccessful Check-In',
                    data: failedValues,
                    color: 'rgb(178,34,34)'
                }
            ]
        });
    };

    //hourly unsuccessful check-in linechart
    $scope.createHourlyUnsuccessfulCheckInChart = function () {

        var series = [];

        $scope.kiosk.forEach(function(element) {
            series.push({
                name: element.ID,
                data: $scope.hourlyData[element.ID].unsuccess,
                fontFamily: 'Arial',
                color: element.color,
            });
        });

        $scope.hourlyUnsuccessfulCheckInChart = Highcharts.chart('hourly-unsuccessful-check-in-container', {
            chart: {
                type: 'line',
                height: (7.9 / 16 * 100) + '%'
            },
            title: {
                text: 'Hourly Failed Check-In Trend',
                margin: 50
            },
            subtitle: {
                fontFamily: 'Arial'
            },
            xAxis: {
                type: 'datetime',
                title: {
                    enabled: true,
                    text: 'Time',
                    fontFamily: 'Arial'
                },
                labels: {
                    format: '{value:%H:%M:%S}'
                },
                startOnTick: true,
                endOnTick: true,
                showLastLabel: true,
                tickInterval: 3600000
            },
            yAxis: {
                title: {
                    text: 'No. Failed Check-In'
                },
                tickInterval: 2
            },
            plotOptions: {
                series: {
                    turboThreshold: 10000
                },
                line: {
                    dataLabels: {
                        enabled: false
                    },
                    enableMouseTracking: true,
                    tooltip: {
                        headerFormat: '<b>{series.name}</b><br>',
                        pointFormat: 'Date: {point.x:%b %d, %Y} <br> Time: {point.x:%H:%M:%S} <br> Number: {point.y}',
                        display: true
                    }
                }
            },
            series: series
        });
    };

    //hourly successful check-in chart
    $scope.createHourlyCheckInChart = function () {

        var series = [];

        $scope.kiosk.forEach(function(element) {
            series.push({
                name: element.ID,
                data: $scope.hourlyData[element.ID].success,
                fontFamily: 'Arial',
                color: element.color,
            });
        });

        $scope.hourlyCheckInChart = Highcharts.chart('hourly-check-in-container', {
            chart: {
                type: 'line',
                height: (7.9 / 16 * 100) + '%'
            },
            title: {
                text: 'Hourly Check-In Trend',
                margin: 50
            },
            subtitle: {
                fontFamily: 'Arial'
            },
            xAxis: {
                type: 'datetime',
                title: {
                    enabled: true,
                    text: 'Time',
                    fontFamily: 'Arial'
                },
                labels: {
                    format: '{value:%H:%M:%S}'
                },
                startOnTick: true,
                endOnTick: true,
                showLastLabel: true,
                tickInterval: 3600000
            },
            yAxis: {
                title: {
                    text: 'No. Check-In'
                },
                tickInterval: 3
            },
            plotOptions: {
                series: {
                    turboThreshold: 10000
                },
                line: {
                    dataLabels: {
                        enabled: false
                    },
                    enableMouseTracking: true,
                    tooltip: {
                        headerFormat: '<b>{series.name}</b><br>',
                        pointFormat: 'Date: {point.x:%b %d, %Y} <br> Time: {point.x:%H:%M:%S} <br> Number: {point.y}',
                        display: true
                    }
                }
            },
            series: series
        });
    };

    //hourly heartbeat linechart
    $scope.createHourlyHeartbeatTrendChart = function () {

        var series = [];

        $scope.kiosk.forEach(function(element) {
           series.push({
               name: element.ID,
               data: $scope.hourlyData[element.ID].heartbeat,
               fontFamily: 'Arial',
               color: element.color,
           });
        });

        $scope.hourlyChart = Highcharts.chart('hourly-container', {
            chart: {
                type: 'line',
                height: (7.9 / 16 * 100) + '%'
            },
            title: {
                text: 'Hourly Heartbeat Trend',
                margin: 50
            },
            subtitle: {
                fontFamily: 'Arial'
            },
            xAxis: {
                type: 'datetime',
                title: {
                    enabled: true,
                    text: 'Time',
                    fontFamily: 'Arial'
                },
                labels: {
                    format: '{value:%H:%M:%S}'
                },
                startOnTick: true,
                endOnTick: true,
                showLastLabel: true,
                tickInterval: 3600000
            },
            yAxis: {
                title: {
                    text: 'No. Heartbeat'
                }
            },
            plotOptions: {
                series: {
                    turboThreshold: 10000
                },
                line: {
                    dataLabels: {
                        enabled: false
                    },
                    enableMouseTracking: true,
                    tooltip: {
                        headerFormat: '<b>{series.name}</b><br>',
                        pointFormat: 'Date: {point.x:%b %d, %Y} <br> Time: {point.x:%H:%M:%S} <br> Number: {point.y}',
                        display: true
                    }
                }
            },
            series: series
        });
    };
});

