var myApp = angular.module('vwr', ['checklist-model','firebase','ui.bootstrap','ui.filters','ui.select','ngAnimate','ngMaterial','highcharts-ng']);

/*angular.module('vwr').component('weightModal',
{
		templateUrl: 'js/vwr/templates/weightModal.htm',
		controllerAs: 'weightModalController',
		bindToController: true
});*/



myApp.controller("weightModalController",function ($scope,$http,$filter,$timeout)
{	
	$scope.weightEntered = true; //tells us if the patient's weight has been updated
	$scope.heightsAdded = false; //indicates if the height series has been inserted in the highchart

	$scope.historicalChart = 
	{
		config: {}
	};

	$http({
		url: "./getPatients.pl",
		method: "GET",
	}).then(function (response) 
	{
		$scope.patients = response.data;

		$scope.index = 0;

		$scope.patient = $scope.patients[0];
		$scope.patient.PatientIdRVH = $scope.patient.PatientIdRVH.replace(/'/g,"");
		$scope.patient.PatientIdMGH = $scope.patient.PatientIdMGH.replace(/'/g,"");
		$scope.patient.BSA = $scope.patient.BSA *1;
		$scope.patient.Height = $scope.patient.Height *1;
		$scope.patient.Weight = $scope.patient.Weight *1;

		console.log($scope.patient);
		/*{
			Height: patient.Height,
			Weight: patient.Weight,
			BSA: patient.BSA,
			FirstName: patient.FirstName,
			LastName: patient.LastName,
			PatientIdRVH: patient.PatientIdRVH
		}*/
		
		//===================================================
		// Create a graph using highcharts for the patient's historical height and weight
		//===================================================	
		$scope.getHistoricalMeasurements = function()
		{
			//get the patient's previous heights and weights
			$http({
				url: "./getHistoricalMeasurements.php",
				method: "GET",
				params: {
						patientIdRVH: $scope.patient.PatientIdRVH,
						patientIdMGH: $scope.patient.PatientIdMGH
					}
			}).then(function (response) 
			{
				//separate the weight and height and format data for highcharts
				var weights = [];
				var heights = [];

				angular.forEach(response.data,function (measurement)
				{
					//parse the date
					var year = measurement.Date.substring(0,4);
					var month = measurement.Date.substring(5,7);
					var day = measurement.Date.substring(8,10);
					
					//subtract a month since january is 0
					weights.push([Date.UTC(year,month-1,day),measurement.Weight]);
					heights.push([Date.UTC(year,month-1,day),measurement.Height]);
				});

				//if there are only two points, the x axis in highcharts bugs out so we add another point
				//we'll remove it's tooltip later
				if(weights.length === 2)
				{
					weights = [weights[0],{x:weights[0][0]+1,y:weights[0][1],noTooltip:true},weights[1]];
				}

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
									if($scope.heightsAdded == false)
									{
										if($scope.weightEntered == true)
										{
											//get the current graph with only the weights
											$scope.weightsOnlyChart = angular.copy(this);

											$scope.heightsAdded = true; //put this here or else highcharts goes in an infinite loop and causes a stack error
											console.log($scope.patient.PatientIdRVH);
											//create and send a pdf to Oacis
											//also convert our hightchart into svg, encode it, and give it to the script
											$http({
												url: "./createWeightDocument.pl",
												method: "POST",
												data: window.btoa(unescape(encodeURIComponent($scope.weightsOnlyChart.getSVG()))),
												params:
												{
														patientIdRVH: $scope.patient.PatientIdRVH,
														patientIdMGH: $scope.patient.PatientIdMGH,
														firstName: $scope.patient.FirstName,
														lastName: $scope.patient.LastName
												}
											}).then(function() {
												$timeout(function() {
													$scope.index++;
													$scope.patient = $scope.patients[$scope.index];

													$scope.patient.PatientIdRVH = $scope.patient.PatientIdRVH.replace(/'/g,"");
													$scope.patient.PatientIdMGH = $scope.patient.PatientIdMGH.replace(/'/g,"");
													$scope.patient.BSA = $scope.patient.BSA *1;
													$scope.patient.Height = $scope.patient.Height *1;
													$scope.patient.Weight = $scope.patient.Weight *1;

													$scope.heightsAdded = false;
		
													$scope.getHistoricalMeasurements();
												},1000);
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
												color: 'red',
												unit: ' cm',
												data: heights,
												visible: false
											}
										];

										$scope.heightsAdded = true;
									}
								}
							}
						},
						title: {text:'Historical Measurements'},
						rangeSelector: {
							enabled: true,
							selected: 1
						},
						xAxis: {type:'datetime',
							crosshair: true
						},
						yAxis: {
							title: {text: "Measurement"}
						},
						plotOptions: {
							series: {
								marker: {
									enabled: true,
								},
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

	});
});
