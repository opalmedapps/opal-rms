
Docker setup instructions:

* mkdir tmp/mysql_orms
* mkdir tmp/mysql_log
* cp docker/.env.template docker/.env
    * fill out docker/.env
* put ssl certs in certs/ as server.crt and server.key
* cd docker/
    * docker compose run --rm --user=root app npm install
    * docker compose run --rm app composer install

To run docker containers:
* cd docker
* docker compose up

To enable git hooks:
* sh .githooks/activateHooks.sh

Profile system notes:
* When adding a new column type, run the php/tool/verifyProfileColumns script right after

Setup cronjobs:
* cp config/crunz.yml.template config/crunz.yml
* in config/crunz/yml, set the error file location, mailer settings, and timezone (important, else the cron won't work)
* put in crontab:
    * \* * * * * cd /path/to/config && ../vendor/bin/crunz schedule:run >/dev/null
    * 0 0 * * 7 /usr/local/bin/pm2 restart hes >/dev/null

Setup application:
* cp config/config.conf.template config/config.conf
* fill out config/config.conf
