(function () {
    'use strict';

    angular
        .module('vwr')
        .service('WearableCharts', WearableCharts);

    WearableCharts.$inject = ['$http', '$mdDialog', '$cookies'];

    /* @ngInject */
    function WearableCharts($http, $mdDialog, $cookies) {

        return {
            showWearableDataCharts: showWearableDataCharts,
            getUnreadWearablesDataCounts: getUnreadWearablesDataCounts,
        };

        async function showWearableDataCharts(wearablesURL) {
            try {
                const response = await $http.get(
                    wearablesURL,
                    {
                        'withCredentials': true,
                        'timeout': 10000  // 10 seconds
                    }
                );

                var modalDialog = $mdDialog.confirm(
                    {
                        templateUrl: 'VirtualWaitingRoom/js/vwr/templates/wearableCharts.htm',
                        onComplete: (scope, element, options) => {
                            element.find("img").replaceWith(response.data);
                            element.find("h2").remove();
                        }
                    })
                    .ariaLabel('Smart Devices Data')
                    .clickOutsideToClose(true);
            } catch (e) {
                var modalDialog = $mdDialog.confirm(
                    {
                        templateUrl: 'VirtualWaitingRoom/js/vwr/templates/wearableCharts.htm',
                        onComplete: (scope, element, options) => element.find("img").replaceWith(
                            '<br><h4 style="color:red;">Could not load the charts. Please contact the administrator.</h4>'
                        ),
                    })
                    .ariaLabel('Smart Devices Data')
                    .clickOutsideToClose(true);
            }

            $mdDialog.show(modalDialog);
        }

        async function getUnreadWearablesDataCounts(
            unviewedHealthDataURL,
            patientUUIDsList,
        ) {
            const response = await $http.post(
                unviewedHealthDataURL,
                patientUUIDsList,
                {
                    'headers': {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'x-csrftoken': $cookies.get('csrftoken'),
                    },
                    'withCredentials': true,
                },
            );

            return response?.data;
        }
    }
})();
