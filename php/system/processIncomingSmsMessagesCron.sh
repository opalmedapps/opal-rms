#!/bin/sh

#move from the system's cronjob folder to the scripts folder
relativePath="`dirname \"$0\"`"
cd $relativePath
fullPath=`pwd`
cd $fullPath/

#run the script
while true
do
    php processIncomingSmsMessages.php
    #under 5 seconds, and the cdyne database doesn't update fast enough and the same message is processed twice
    sleep $((5))
done

