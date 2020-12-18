#!/bin/sh

#move from the system's cronjob folder to the ORMS folder
relativePath="`dirname \"$0\"`"
cd $relativePath
fullPath=`pwd`
cd $fullPath/..

#update the checkin file
while true
do
    php ./php/updateCheckinFile.php 1>/dev/null
    sleep 3
done
