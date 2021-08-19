
To enable git hooks:
    sh .githooks/activateHooks.sh

Profile notes:
    -When creating or deleting profiles in the WaitRoomMangement DB, be sure to use the stored procedures (SetupProfile/DeleteProfile respectively).
    -When adding a new column type, run the VerifyProfileColumns procedure right after.

Highcharts:
    To start the highcharts export server (after running npm install):
        pm2 start node_modules/highcharts-export-server/bin/cli.js --name hes -- --enableServer 1

Cronjobs:
    create of copy of config/crunz.yml.template called config/crunz.yml
    set the error file location, mailer settings, and timezone (important, else the cron won't work)
    put in crontab: * * * * * cd /path/to/project/config && ../vendor/bin/crunz schedule:run
