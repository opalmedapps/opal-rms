angular.module('vwr').component('weightModal',
{
    templateUrl: 'js/vwr/templates/weightModal.htm',
    controllerAs: 'weightModalController',
    bindToController: true
});

function weightModalController ($scope,$http,$uibModalInstance,$filter,patient)
{
    let weightEntered = false; //tells us if the patient's weight has been updated

    $scope.invalidMrnDetected = false; //indicates if an Id not belonging to the patient was in one of the weights
    $scope.patient =
    {
        height: patient.Height,
        weight: patient.Weight,
        bsa: patient.BSA,
        firstName: patient.FirstName,
        lastName: patient.LastName,
        mrn: patient.Mrn,
        site: patient.Site,
        patientId: patient.PatientId
    }

    let mostRecentHeight;
    let mostRecentWeight;
    let mostRecentBSA;

    $scope.warnForWeight = false;
    $scope.warnForHeight = false;
    $scope.warnForBSA = false;
    $scope.updateConfirmed = true;

    $scope.historicalChart = {
        config: {}
    };

    getHistoricalMeasurementsAsync().then(r => {$scope.historicalChart.config = r});

    //calculate a new BSA for the patient when the height or weight are changed
    function recalculateBSA()
    {
        //use the Dubois formula
        $scope.patient.bsa = 0.007184*Math.pow($scope.patient.weight,0.425)*Math.pow($scope.patient.height,0.725);
        $scope.patient.bsa = Math.round($scope.patient.bsa*100)/100;
    };

    //ensure the height and BSA don't differ too much from the previous tiime the patient was measured
    //we don't expect the height or bsa to vary significantly
    //threshold is 10% difference for weight, 5% for height and BSA
    $scope.verifyMeasurements = function()
    {
        recalculateBSA();

        var weightDiff = (mostRecentWeight !== 0) ? 100 * Math.abs($scope.patient.weight - mostRecentWeight) / mostRecentWeight : 0;
        var heightDiff = (mostRecentHeight !== 0) ? 100 * Math.abs($scope.patient.height - mostRecentHeight) / mostRecentHeight : 0;
        var bsaDiff = (mostRecentBSA !== 0) ? 100 * Math.abs($scope.patient.bsa - mostRecentBSA) / mostRecentBSA : 0;

        $scope.warnForWeight = (weightDiff > 10);
        $scope.warnForHeight = (heightDiff > 5);
        $scope.warnForBSA = (bsaDiff > 5);

        $scope.updateConfirmed = (!$scope.warnForHeight && !$scope.warnForBSA);
    }

    $scope.updateWeightChart = function()
    {
        if(!$scope.patient.height)
        {
            $scope.patient.height = 0;
            $scope.patient.bsa = 0;
        }

        if(!$scope.updateConfirmed)
        {
            $scope.updateConfirmed = true;
            return false;
        }

        $http({
            url: "/php/api/private/v1/patient/measurement/insertMeasurement",
            method: "POST",
            data: {
                patientId: $scope.patient.patientId,
                height: $scope.patient.height,
                weight: $scope.patient.weight,
                bsa: $scope.patient.bsa,
                sourceId: patient.SourceId,
                sourceSystem: patient.CheckinSystem
            }
        }).then(function(_)
        {
            weightEntered = true;
            //update the chart with the new value
            getHistoricalMeasurementsAsync().then(r => {$scope.historicalChart.config = r});
        });
    }

    //close the modal when the user presses the close button
    $scope.accept = function()
    {
        $uibModalInstance.close(weightEntered);
    };

    //create a highcharts graph using highcharts for the patient's historical height and weight
    function getHistoricalMeasurementsAsync()
    {
        return $http({
            url: "/php/api/private/v1/patient/measurement/getHistoricalChart",
            method: "GET",
            params: {
                patientId: $scope.patient.patientId
            }
        }).then(function (response)
        {
            let chart = response.data.data;

            //add the tooltip function for the display
            chart.tooltip =
            {
                formatter: function()
                {
                    if(this.x % 2 === 0)
                    {
                        tooltipString = '<span style="font-size: 10px">'+$filter('date')(this.x,'EEEE, MMM dd, yyyy');
                        angular.forEach(this.points,function (point)
                        {
                            tooltipString += '</span><br/><span style="color:'+point.color+'">\u25CF</span> '+point.series.name+': <b>'+point.y+'</b><br/>';
                        });

                        return tooltipString;
                    }
                    else {return false;}
                },
                useHTML: true,
                shared: true,
                snap: 10,
            };

            //if any of the dots are red, then there is an invalid id for one of the measurements
            $scope.invalidMrnDetected = chart.series[0].data.some(x => x.color === "red");

            //store the most recent measurement for display and verification
            mostRecentWeight = chart.series[0].data.slice(-1)[0].y;
            mostRecentHeight = chart.series[1].data.slice(-1)[0].y;
            mostRecentBSA    = chart.series[2].data.slice(-1)[0].y;

            $scope.patient.weight = mostRecentWeight;
            $scope.patient.height = mostRecentHeight;
            $scope.patient.bsa = mostRecentBSA;

            //don't display the heights and bsas by default
            chart.series[1].visible = false;
            chart.series[2].visible = false;

            //verify the measurements
            $scope.verifyMeasurements();

            return chart;
        });
    };
}
