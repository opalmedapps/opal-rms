(function () {
    'use strict';

    angular
        .module('vwr')
        .service('WearableCharts', WearableCharts);

    WearableCharts.$inject = ['$http', '$mdDialog'];

    /* @ngInject */
    function WearableCharts($http, $mdDialog) {

        return {
            showWearableDataCharts: showWearableDataCharts
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
                    .clickOutsideToClose(true);
            } catch (e) {
                var modalDialog = $mdDialog.confirm(
                    {
                        templateUrl: 'VirtualWaitingRoom/js/vwr/templates/wearableCharts.htm',
                        onComplete: (scope, element, options) => element.find("img").replaceWith(
                            '<br><h4 style="color:red;">Could not load the charts. Please contact the administrator.</h4>'
                        ),
                    })
                    .clickOutsideToClose(true);
            }

            $mdDialog.show(modalDialog);
        }
    }
})();
