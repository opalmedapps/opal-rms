
To enable git hooks:
sh .githooks/activateHooks.sh

Profile notes:
    -When creating or deleting profiles in the WaitRoomMangement DB, be sure to use the stored procedures (SetupProfile/DeleteProfile respectively).
    -When adding a new column type, run the VerifyProfileColumns procedure right after.


Highcharts:
To start the highcharts export server (after running npm install):
    pm2 start node_modules/highcharts-export-server/bin/cli.js --name hes -- --enableServer 1

Virtual Waiting Room notes:
Put in crontab: * * * * * flock -n /var/www/OnlineRoomManagementSystem/tmp/ormsLockerProd.lock /var/www/OnlineRoomManagementSystem/php/system/updateORMSCheckinFile.sh || exit 0
