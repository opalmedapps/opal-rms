//bootstrap the main controller so that the page loads
angular.element(document).ready(function()
{
    //we need the profile settings to continue loading the rest of the page
    //unfortunately, the controller loads much faster than the profile script and so we'll end up with a bunch of undefined values if we continue
    //so we get the profile settings first, then bootstrap the page

    //check if a (existing) profile was specified
    //if not, return the user to the index page
    //also return if there is no profile match or a clinical area hasn't been specified
    var $http = angular.injector(['ng','mockApp']).get('$http');
    var $window = angular.injector(['ng','mockApp']).get('$window');
    var urlParams = angular.injector(['ng','mockApp']).get('$location').search();

    if(urlParams.profile)
    {
        $http({
            url: "php/profile/getProfileDetails.php",
            method: "GET",
            params:
            {
                profileId: urlParams.profile,
                clinicalArea: urlParams.clinicalArea
            }
        }).then(function (response)
        {
            if(angular.equals(response.data,{}))
            {
                $window.location.href = "./";
            }

            //finally, after we have all necessary settings, bootstrap the true page controller
            angular.module('vwr').constant('ProfileSettings',response.data);
            angular.bootstrap(document,['vwr']);
        });
    }
    else
    {
        $window.location.href = "./";
    }

});
