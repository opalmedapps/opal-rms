# ORMS

The Online Room Management System (ORMS) provides a clinical viewer (aka. live clinician dashboard), a virtual waiting room and separate frontends for kiosks and TV screens.

## Requirements

This project has the following requirements to be available on your system:

* [Docker Desktop](https://docs.docker.com/desktop/) (or Docker Engine on Linux)
* Optional: node 16 (needed if you want to invoke `npm` commands locally)

## Getting Started

After cloning this repo, follow the below steps to get started.

### Configuration

All configuration is stored within the `.env` file and `config/config.conf`. The `.env` file consists mostly of the database settings for the respective containers whereas the config file contains the application-specific settings.

Copy the `.env.sample` to `.env` and adjust the values as necessary. You only need to make modifications if you don't want to use the default values or any of the ports is already allocated on your machine.

These configuration parameters are read by `docker compose`.

### Docker

This project comes with a `docker-compose.yml` file providing you with a database and the app in their respective containers. In addition, the following services are required:

* `memcached` for storing the sessions in-memory
* `highcharts` for generating charts
* `crunz` for running a cron daemon

The ORMS app is built with a custom image (defined in `Dockerfile`).

Execute the following command to start up the containers: `docker compose up app` (note that for the time being only start `app` and the services it depends on)

If you need to rebuild the app, you can either run `docker compose build app` before starting the container or `docker compose up app --build` to force a rebuild.

To connect to the app container, run `docker compose exec app bash` (or any specific command instead of `bash`).

### Environment-specific configuration

* put ssl certs in certs/ as server.crt and server.key

Setup cronjobs:

* cp config/crunz.yml.template config/crunz.yml
* in config/crunz/yml, set the error file location, mailer settings, and timezone (important, else the cron won't work)

## Updating dependencies

The current `Dockerfile` is using a [multi-stage build](https://docs.docker.com/build/building/multi-stage/) to avoid having unnecessary dependencies in the final image. For example, to run ORMS `node`, `npm` and `composer` are not needed.

If you need to run `npm` commands you can either do it using your locally installed `node` or run a separate container:

```shell
docker run --rm -it -v $PWD:/app --workdir /app node:16 npm install markdownlint2-cli
```

The same concept applies to `composer.

## Open Issues

The following notes were adding by Victor and have been slightly edited for readability.

### Remaining Containerization Issues

* A mailserver hasn't been set up/emailing hasn't been tested.
* The cron service won't run if a previous run hasn't terminated. Unfortunately, the ORMS cron is designed to never terminate. The cron service has to be restarted when changing the config.conf settings in order for the changes to have an effect.
* Pdflatex isn't set up on the application container so generating pdfs won't work.
* git hooks currently do not work since everything is done in the container and a multi-stage Dockerfile is used

### Git hooks

To enable git hooks: `sh .githooks/activateHooks.sh`

## Profile system notes

* When adding a new column type, run the php/tool/verifyProfileColumns script right after
