New version of ORMS that has been remade from the old one.

Virtual Waiting Room notes:

Quick comments:
    -When creating or deleting profiles in the WaitRoomMangement DB, be sure to use the stored procedures (SetupProfile/DeleteProfile respectively).
    -When adding a new column type, run the VerifyProfileColumns procedure right after.

To start the highcharts export server (after running npm install):
pm2 start node_modules/highcharts-export-server/bin/cli.js --name hes -- --enableServer 1

To enable git hooks:
sh .githooks/activateHooks.sh
