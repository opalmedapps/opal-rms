angular.module('vwr').component('weightModal',
{
		templateUrl: 'js/vwr/templates/weightModal.htm',
		controllerAs: 'weightModalController',
		bindToController: true
});

function weightModalController ($scope,$http,$uibModalInstance,$filter,patient)
{	
	$scope.weightEntered = false; //tells us if the patient's weight has been updated
	$scope.heightsAdded = false; //indicates if the height series has been inserted in the highchart
	$scope.bsaAdded = false;

	$scope.invalidIdDetected = false; //indicates if an Id not belonging to the patient was in one of the weights
	$scope.invalidIdDetected = false; //indicates if an Id not belonging to the patient was in one of the weights

	$scope.patient =
	{
		Height: patient.Height,
		Weight: patient.Weight,
		BSA: patient.BSA,
		FirstName: patient.FirstName,
		LastName: patient.LastName,
		PatientIdRVH: patient.PatientIdRVH
	}

	$scope.mostRecentHeight;
	$scope.mostRecentWeight;
	$scope.mostRecentBSA;

	$scope.warnForWeight = false;
	$scope.warnForHeight = false;
	$scope.warnForBSA = false;
	$scope.updateConfirmed = true;

	//calculate a new BSA for the patient when the height or weight are changed
	$scope.recalculateBSA = function()
	{
		//use the Dubois formula
		$scope.patient.BSA = 0.007184*Math.pow($scope.patient.Weight,0.425)*Math.pow($scope.patient.Height,0.725);
		$scope.patient.BSA = Math.round($scope.patient.BSA*100)/100;
	}

	//ensure the height and BSA don't differ too much from the previous tiime the patient was measured
	//we don't expect the height or bsa to vary significantly
	//threshold is 10% difference for weight, 5% for height and BSA
	$scope.verifyMeasurements = function()
	{
		$scope.recalculateBSA();

		var weightDiff = ($scope.mostRecentWeight != 0) ? 100 * Math.abs($scope.patient.Weight - $scope.mostRecentWeight) / $scope.mostRecentWeight : 0;
		var heightDiff = ($scope.mostRecentHeight != 0) ? 100 * Math.abs($scope.patient.Height - $scope.mostRecentHeight) / $scope.mostRecentHeight : 0;
		var bsaDiff = ($scope.mostRecentBSA != 0)?  100 * Math.abs($scope.patient.BSA - $scope.mostRecentBSA) / $scope.mostRecentBSA : 0;

		$scope.warnForWeight = (weightDiff > 10) ? true : false;
		$scope.warnForHeight = (heightDiff > 5) ? true : false;
		$scope.warnForBSA = (bsaDiff > 5) ? true : false;

		$scope.updateConfirmed = (!$scope.warnForHeight && !$scope.warnForBSA) ? true : false;
	}

	$scope.updateWeightChart = function()
	{
		if(!$scope.patient.Height) 
		{
			$scope.patient.Height = 0;
			$scope.patient.BSA = 0;
		}

		if(!$scope.updateConfirmed)
		{
			$scope.updateConfirmed = true;
			return 0;
		}

		$http({
			url: "php/updatePatientMeasurements.php",
			method: "GET",
			params: {
				patientIdRVH: patient.PatientIdRVH,
				patientIdMGH: patient.PatientIdMGH,
				ssnFirstThree: patient.SSN,
				height: $scope.patient.Height,
				weight: $scope.patient.Weight,
				bsa: $scope.patient.BSA,
				appointmentId: patient.AppointmentId
			}
		}).then(function (response) 
		{
			$scope.weightEntered = true;
			$scope.heightsAdded = false;
			$scope.bsaAdded = false;
			$scope.getHistoricalMeasurements(); //update the chart with the new value
		});
	}

	//close the modal when the user presses the close button
	$scope.accept = function()
	{
		$uibModalInstance.close($scope.weightEntered);
	}

	/*$scope.cancel = function()
	{
		$uibModalInstance.dismiss();
	}*/
	
	//===================================================
	// Create a graph using highcharts for the patient's historical height and weight
	//===================================================
	$scope.historicalChart = 
	{
		config: {}
	};

	$scope.getHistoricalMeasurements = function()
	{
		//get the patient's previous heights and weights
		$http({
			url: "php/getHistoricalMeasurements.php",
			method: "GET",
			params: {
					patientIdRVH: patient.PatientIdRVH,
					patientIdMGH: patient.PatientIdMGH
				}
		}).then(function (response) 
		{
			//separate the weight and height and format data for highcharts
			var weights = [];
			var heights = [];
			var bsas = [];

			angular.forEach(response.data,function (measurement)
			{
				var dotColorWeight = 'blue';
				var dotColorHeight = 'green';
				var dotColorBSA = 'purple';

				//check if the patient id of the weight is valid
				if(measurement.PatientId != patient.PatientIdRVH)
				{
					$scope.invalidIdDetected = true;
					dotColorWeight = 'red';
					dotColorHeight = 'red';
					dotColorBSA = 'red';
				}

				//parse the date
				var year = measurement.Date.substring(0,4);
				var month = measurement.Date.substring(5,7);
				var day = measurement.Date.substring(8,10);
				
				//subtract a month since january is 0
				weights.push({x: Date.UTC(year,month-1,day) +86400000,y: measurement.Weight, color: dotColorWeight});
				heights.push({x: Date.UTC(year,month-1,day) +86400000,y: measurement.Height, color: dotColorHeight});
				bsas.push({x: Date.UTC(year,month-1,day) +86400000,y: measurement.BSA, color: dotColorBSA});
			});

			//if there are only two points, the x axis in highcharts bugs out so we add another point
			//we'll remove it's tooltip later
			if(weights.length === 2)
			{
				weights = [weights[0],{x:weights[0][0]+1,y:weights[0][1],noTooltip:true},weights[1]];
			}

			//store the most recent measurement for verification
			$scope.mostRecentWeight = weights[weights.length -1].y;
			$scope.mostRecentHeight = heights[heights.length -1].y;
			$scope.mostRecentBSA = bsas[bsas.length -1].y;

			$scope.patient.Weight = $scope.mostRecentWeight;
			$scope.patient.Height = $scope.mostRecentHeight;
			$scope.patient.BSA = $scope.mostRecentBSA;

			//verify the measurements
			$scope.verifyMeasurements();

			//generate chart

			//generate only a chart with weights at first
			$scope.historicalChart =
			{
				config: 
				{
					chart: {
						type: 'line',
						panning: true,
						//zoomType: 'x',
						//panKey: 'shift'
						events: {
							load: function(event)
							{
								if($scope.heightsAdded == false && $scope.bsaAdded == false)
								{
									if($scope.weightEntered == true)
									{
										//get the current graph with only the weights
										var weightsOnlyChart = angular.copy(this);

										$scope.heightsAdded = true; //put this here or else highcharts goes in an infinite loop and causes a stack error
										$scope.bsaAdded = true;

										//create and send a pdf to Oacis
										//also convert our hightchart into svg, encode it, and give it to the script
										$http({
											url: "perl/createWeightDocument.pl",
											method: "POST",
											data: window.btoa(weightsOnlyChart.getSVG()),
											params:
											{
													patientIdRVH: patient.PatientIdRVH,
													patientIdMGH: patient.PatientIdMGH,
													firstName: patient.FirstName,
													lastName: patient.LastName
											}
										});
									}

									//add in the heights now
									$scope.historicalChart.config.series = 
									[
										{
											showInLegend: true,
											name: "Weight (kg)",
											color: "blue",
											unit: ' kg',
											data: weights
										},
										{
											showInLegend: true,
											name: "Height (cm)",
											color: 'green',
											unit: ' cm',
											data: heights,
											visible: false
										},
										{
											showInLegend: true,
											name: "BSA (m<sup>2</sup>)",
											color: "purple",
											unit: ' m<sup>2</sup>',
											data: bsas,
											visible: false
										}
									];

									$scope.heightsAdded = true;
									$scope.bsaAdded = true;
								}
							}
						}
					},
					title: {text:'Historical Measurements'},
					rangeSelector: {
						enabled: true,
						selected: 1
					},
					xAxis: {
						type:'datetime',
						crosshair: true
					},
					yAxis: {
						title: {text: "Measurement"}
					},
					legend: {
						useHTML: true
					},
					plotOptions: {
						series: {
							marker: {enabled: true,},
							point: {
								events: {
									//click: function() {console.log(this);},
									//mouseOver: function() {console.log(this);}
								}
							},
							stickyTracking: false,
							//findNearestPointBy: 'xy'
						}	
					},
					tooltip: {
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
						snap: 10
					},	
					series: [
						{
							showInLegend:true,
							name: "Weight (kg)",
							color: "blue",
							unit: ' kg',
							data: weights
						}
					],
					exporting:{fallbackToExportServer: false}
				}
			};
		});
	}
	$scope.getHistoricalMeasurements();

	//End Highcharts Section =================================================

}
