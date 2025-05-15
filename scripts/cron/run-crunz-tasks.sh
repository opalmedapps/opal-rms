#!/bin/bash

# The Crunz binary should be executed from the ./config directory so it uses the configurations set in the crunz.yml
cd /var/www/orms/config
/usr/local/bin/php ./../vendor/bin/crunz schedule:run 2>>/var/log/cron_errors.log
