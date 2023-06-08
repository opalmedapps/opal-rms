#!/bin/bash

cd /var/www/orms/config
/usr/local/bin/php ./../vendor/bin/crunz schedule:run 2>>/var/log/cron_errors.log
