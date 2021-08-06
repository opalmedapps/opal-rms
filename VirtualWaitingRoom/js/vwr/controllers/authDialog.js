angular.module('vwr').component('authDialog',{
    templateUrl: 'js/vwr/templates/authDialog.htm',
    controller: authDialogController
});

function authDialogController($scope,$http,$cookies,$mdDialog)
{
    $scope.page = {
        username: $cookies.get("lastUsedUsername") ?? "", //if the user has previously authenticated, a cookie with the username should exist
        password: "",
        message: ""
    };
    $scope.Title = "Authenticate to complete action";

    $scope.authenticate = async function()
    {
        await $http({
            url: "./php/authenticateUser",
            method: "POST",
            data: {
                username: $scope.page.username,
                password: $scope.page.password
            }
        })
        .then( _ => {
            //store the last authenticated username in case the user is reviewing multiple questionnaires
            $cookies.put("lastUsedUsername",$scope.page.username);
            $mdDialog.hide($scope.page.username);
        })
        .catch( _ => {
            $scope.page.message = "Authentication failed";
        });

        $scope.page.password = ""; //clear the password field
    }
}
