
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

Docker issues:
* A mailserver hasn't been set up / emailing hasn't been tested.
* The cron service won't run if a previous run hasn't terminated. Unfortunately, the ORMS cron is designed to never terminate. The cron service has to be restarted when changing the config.conf settings in order for the changes to have an effect.
* Pdflatex isn't set up on the application container so generating pdfs won't work.

To enable git hooks:
* sh .githooks/activateHooks.sh

Profile system notes:
* When adding a new column type, run the php/tool/verifyProfileColumns script right after

Setup application:
* cp config/config.conf.template config/config.conf
* fill out config/config.conf

Setup cronjobs:
* cp config/crunz.yml.template config/crunz.yml
* in config/crunz/yml, set the error file location, mailer settings, and timezone (important, else the cron won't work)
