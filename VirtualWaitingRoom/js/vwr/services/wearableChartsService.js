// SPDX-FileCopyrightText: Copyright (C) 2023 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

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

        async function showWearableDataCharts(wearablesURL, patientUUID) {
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
                        onComplete: async function (scope, element, options) {
                            element.find("img").replaceWith(response.data);
                            element.find("h2").remove();

                            // Find backend host's address from the wearables URL.
                            const wearableDataChartsURL = new URL(wearablesURL);
                            const backendHost = wearableDataChartsURL.origin;

                            // Mark patient's wearable data as viewed
                            await $http.patch(
                                backendHost + `/api/patients/${patientUUID}/health-data/quantity-samples/viewed/`,
                                {},
                                {
                                    'headers': {
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                        'x-csrftoken': $cookies.get('csrftoken'),
                                    },
                                    'withCredentials': true,
                                    'timeout': 3000  // 3 seconds
                                }
                            );
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
