//This is the file where you hard-code kiosk information.

/*How to add a new kiosk into the system (For removing kiosk, you just have to undo each step)
1. Inside the kiosks array (in the callback function), intialize kiosk object accordingly
2. Add an integer to hashtable, where each kiosk object is assigned a unique integer (this is going to define a unique line in highcharts)
*/

var app = angular.module("kioskDisplay", ['checklist-model']);

app.factory('KioskInfo', function() {

    var kiosks = [
        {
            ID: 'DS1_1',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(155, 18, 200, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        },
        {
            ID: 'DS1_2',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(142, 242, 27, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        },
        {
            ID: 'DRC_1',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(223, 83, 83, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        },
        {
            ID: 'DRC_2',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(0, 102, 102, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        },
        {
            ID: 'DRC_3',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(255, 252, 0, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        }, {
            ID: 'Ortho_1',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(40, 121, 102, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        }, {
            ID: 'Ortho_2',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(0, 255, 255, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        }, {
            ID: 'Reception',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(230, 0, 172, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        }, {
            ID: 'ReceptionOrtho',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(51, 102, 153, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        }, {
            ID: 'ReceptionS1',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(153, 102, 51, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        }, {
            ID: 'ReceptionRC',
            success: 0,
            unsuccess: 0,
            heartbeat: 0,
            color: 'rgba(255, 102, 0, .5)',
            averageHeartBeatInterval: 0,
            standardDeviation: 0,
            minimumInterval: 0,
            maximumInterval: 0,
            medianInterval: 0,
            irregular: 0
        }
    ];

    //message objects
    var messages = [
        {
            message: 'Heartbeat',
            color: 'rgba(142, 242, 27, .5)',
            property: 'heartbeat',
            symbol: 'diamond'
        },
        {
            message: 'Successful Check-In',
            color: 'rgb(100,149,237)',
            property: 'success',
            symbol: 'triangle'
        },
        {
            message: 'Unsuccessful Check-In',
            color: '#9400D3',
            property: 'unsuccess',
            symbol: 'triangle-down'
        }
    ];

    //each kiosk is assigned a unique integer to draw a horizontal line
    var hashTable = {
        DS1_1: 1,
        DS1_2: 2,
        DRC_1: 3,
        DRC_2: 4,
        DRC_3: 5,
        Ortho_1: 6,
        Ortho_2: 7,
        Reception: 8,
        ReceptionOrtho: 9,
        ReceptionS1: 10,
        ReceptionRC: 11
    };

    return {

        getKiosks: function () {
            return kiosks;
        },
        getMessages: function () {
            return messages;
        },
        getHeartBeatProperty: function () {
            return messages[0].property;
        },
        getSuccessfulCheckInProperty: function () {
            return messages[1].property;
        },
        getUnsuccessfulCheckInProperty: function () {
            return messages[2].property;
        },
        getHashTable: function () {
            return hashTable;
        },
        getHeartBeatColor: function () {
            return messages[0].color;
        },
        getSuccessfulCheckInColor: function () {
            return messages[1].color;
        },
        getUnsuccessfulCheckInColor: function () {
            return messages[2].color;
        },
        getCorrespondingMessageProperty: function (message) {
            for (var i = 0; i < messages.length; i++) {
                if (messages[i].message === message) {
                    return messages[i].property;
                }
            }
            return undefined
        },
        getMessageSymbol: function (message) {
            for (var i = 0; i < messages.length; i++) {
                if (message === messages[i].property || message === messages[i].message) {
                    return messages[i].symbol;
                }
            }
        }
    };
});