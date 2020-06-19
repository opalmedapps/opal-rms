#!/bin/sh

#move from the call location to the script location
scriptPath="`dirname \"$0\"`"
cd $scriptPath

#install the highcharts export server globally
ACCEPT_HIGHCHARTS_LICENSE=y HIGHCHARTS_VERSION=8.0.0 HIGHCHARTS_USE_MAPS='' HIGHCHARTS_USE_GANTT='' HIGHCHARTS_USE_STYLED='' HIGHCHARTS_MOMENT='' npm install highcharts-export-server@2.0.24 -g --unsafe-perm --strict-ssl false

#replace an erroneous file with a working one
globalNodePath=`npm root -g`

cp ./export.html $globalNodePath/highcharts-export-server/phantom/

