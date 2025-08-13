<!--
SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>

SPDX-License-Identifier: AGPL-3.0-or-later
-->

# ORMS

The Opal Room Management System (ORMS) provides a clinical viewer (aka. live clinician dashboard), a virtual waiting room and separate frontends for kiosks and TV screens.

## Requirements

This project has the following requirements to be available on your system:

* [Docker Desktop](https://docs.docker.com/desktop/) (or Docker Engine on Linux)
* Composer for installing `PHP` code quality dependencies that used in git hooks
* Optional: node v20 (needed if you want to invoke `npm` commands locally)

## Getting Started

After cloning this repo, follow the below steps to get started.

### Configuration

All configuration is stored within the `.env` file to follow the [12factor app methodology](https://12factor.net/config) on storing config in the environment. This means that any setting that depends on the environment the app is run in should be exposed via the `.env`.

Copy the `.env.sample` to `.env` and adjust the values as necessary. You need to set `FIREBASE_CONFIG_PATH` and `FIREBASE_BRANCH` for the `Virtual Waiting Room` page. The firebase configuration file needs to be present in `FIREBASE_CONFIG_PATH`. In the firebase configuration file, `apiKey`, `authDomain`, `databaseURL` and `projectId` must be defined.

These configuration parameters are read by `docker compose` and by `php/class/Config.php` (via [`phpdotenv`](https://github.com/vlucas/phpdotenv)).

If your database is being run with secure transport required (SSL/TLS traffic encryption), also update the values for the SSL environment variables: `DATABASE_USE_SSL=1` and `SSL_CA=/var/www/orms/certs/ca.pem` after copying the `ca.pem` file into the certs directory of ORMs. Detailed instructions on how to generate SSL certificates can be found either in the [documentation repository](https://gitlab.com/opalmedapps/docs/-/blob/main/docs/guides/self_signed_certificates.md) or in the [db-docker README](https://gitlab.com/opalmedapps/db-docker).

### Add the `.npmrc` file (Optional)

This project uses [AngularJS](https://angularjs.org/) which reached end of life in January 2022.
A long-term support version of AngularJS can be used instead, provided by [HeroDevs](https://www.herodevs.com/support/nes-angularjs).
If you have an `npm` token to retrieve this version from their registry, place the `.npmrc` file containing the credentials in the root directory.

### Docker

This project comes with a `docker-compose.yml` file providing you with `db-orms` and `db-log` databases, as well as the `app` in their respective containers. In addition, the following services are required:

* `memcached` for storing the sessions in-memory
* `crunz` for running a cron daemon

The ORMS app is built with a custom image (defined in `Dockerfile`).

Execute the following command to start up the containers:

```shell
docker compose up
```

**Note:** The ORMS application is not available in the root path but rather under `/orms`. For example: http://localhost:8086/orms

If you need to rebuild the containers, you can either run `docker compose build` before starting the container or `docker compose up --build` to force a rebuild. To rebuild containers without Docker’s build cache, run: `docker compose build --no-cache`.

To connect to the app container, run `docker compose exec app bash` (or any specific command instead of `bash`).

### Installing and Updating Dependencies

The current `Dockerfile` is using a [multi-stage build](https://docs.docker.com/build/building/multi-stage/) to avoid having unnecessary dependencies in the final image. For example, to run ORMS `node`, `npm` and `composer` are not needed.

#### NodeJS & NPM

If you need to run `npm` commands you can either do it using your locally installed `node` or run a separate container:

```shell
docker run --rm -it -v $PWD:/app --workdir /app node:20 npm install markdownlint2-cli
```

#### Composer and Code Quality Tools

For installing `composer` dependencies, the same concept is used as for the `npm` dependencies.

In order for linting, type checking, unit testing etc., to be available in your IDE and `githooks`, we recommend to install the dependencies inside working/root directory. Although the `Docker` container automatically installs all the required dependencies, they cannot be seen by the IDE for the code quality tools and `githooks`.

To quickly install the `composer` in the current directory, run the following script in your terminal:

```shell
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === 'baf1608c33254d00611ac1705c1d9958c817a1a33bce370c0595974b342601bd80b92a3f46067da89e3b06bff421f182') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

After running the installer, you can run this to move `composer.phar` to a directory that is in your path, so you can access it globally.

```bash
mv composer.phar /usr/local/bin/composer
```

For more details look [here](https://getcomposer.org/download/) and [here](https://getcomposer.org/doc/00-intro.md#globally).

After installing `composer`, run one of the following commands for installing `PHP` dependencies:

```bash
./composer.phar install
```

or

```bash
composer install
```

### Create Required Data Records For Testing

#### Clinical Viewer and Virtual Waiting Room

For testing `Clinical Viewer` and `Virtual Waiting Room` locally:

* Set `OpalUUID` field in the `Patient` table. The value should be copied from the `Patient.UUID` field in the `django-backend`
* Set `OpalPatient` field in the `Patient` table to `1`
* Create a record in the `MediVisitAppointmentList` table. For example:

```text
'1','1','2023-05-31 18:09:00','2023-05-31','18:09:00','0','1','Aria','1','Open','Active','2023-05-30 00:00:00','1',NULL,NULL
```

> :warning: **Note:**  For the `ScheduledDateTime`, `ScheduledDate`, `ScheduledTime` fields, the date should be the same as the date when the `Clinical Viewer`/`Virtual Waiting Room` test is performed, and the time should be set to the later/future time of the day (e.g., an appointment cannot be in the past). For the `Status` and `MediVisitStatus` fields it should be set to `Open` and `Active` respectively.

**Note:** Most of the data specified above can be inserted in the `orms` database when setting up your database container in db-docker, just by following the README and executing the test data insertion commands for `orms`:

```bash
docker compose run --rm alembic python -m db_management.run_sql_scripts orms db_management/ormsdb/data/initial/
```

```bash
docker compose run --rm alembic python -m db_management.run_sql_scripts orms db_management/ormsdb/data/test/
```

However you will still have to edit the `ScheduledDateTime`, `ScheduledDate`, `ScheduledTime` fields to match the current day of testing.

For the `Virtual Waiting Room` page, make sure that the provided Firebase settings are correct (e.g., in the browser's console, there are no Firebase errors when you  go to the `Default Profile - Virtual Waiting Room` page).

To see the appointment on the `Clinical Viewer` page:

* Click on `Show Menu`
* Disable the `Questionnaire Filter`
* Disable the `Additional Filters`
* Click `Submit`

To see the appointment on the `Virtual Waiting Room` page:

* On the `Virtual Waiting Room Menu`, choose `Default Profile`
* Include to the filter the scheduled appointments by clicking on the `Scheduled` button
* Click on the `USER SELECTED RESOURCE(S)`, and in the modal dialog check the `Test Code` and click `OK`

> :warning: **Note:**  `Virtual Waiting Room` requires `./tmp/1.vwr.json` file that is created and updated by `cron`, otherwise the page will not work. For testing purposes, in case the `cron` does not work, log in to the `orms` container and generate the file by running: `php php/cron/generateVwrAppointments.php`.

#### Weight PDF reports

For testing the weight PDFs locally:

* In the `.env`, set `SEND_WEIGHTS=1` and `VWR_CRON_ENABLED=1`
* Copy and rename `config/crunz.yml.template` to `config/crunz.yml`. Then edit the required fields
* In the `orms` database, update existing record in the `Hospital` table by setting `HospitalCode` field to `RVH`
* In the `php/class/External/OIE/Export.php` file, add the call to pdf generation function before the line that makes OIE call. Note: the old pdf generator function is deprecated and has been removed. Please create a new one before testing.

* Comment the following line in the `php/class/Document/Pdf.php` file:

```php
if(file_exists("$fullFilePath.pdf")) unlink("$fullFilePath.pdf");
```

* Login to the ORMS and find session cookies
* Run the following curl command (NOTE! you need to use your cookie values that found in the previous step)

```bash
curl 'http://127.0.0.1:8086/php/api/private/v1/patient/measurement/insertMeasurement' -X 'POST' -H 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/ avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7' -H 'Cookie: adminer_key=your-adminer_key; adminer_settings=; PHPSESSID=your-PHPSESSID; sessionid=your-sessionid; adminer_permanent=; adminer_sid=your-adminer_sid; specialityGroupId=1; specialityGroupName=Test%20Group; clinicHubId=1; clinicHubName=Test%20Hub; hospitalCode=RVH; csrftoken=your-csrftoken' -H 'Referer: http://127.0.0.1:8086/' -d "patientId=1&height=180&weight=80&bsa=50&sourceId=1&sourceSystem=BBB"
```

* Check `tmp` folder, it should contain generated weights PDF file.

### Pre-commit

To enable git pre-commit hooks, run the following command:

```shell
sh .githooks/activateHooks.sh
```

> :warning: **Note:**  If you are using a git GUI tool (such as `Sourcetree`) the path might not be set up correctly and pre-commit might not be able to find `php-cs-fixer`, `phpstan`, and `psalm`. Currently, the solution is unknown and needs to be investigated.

### Recommended IDE Extensions

#### VSCode

This project contains recommendations for vscode extensions (see `.vscode/extensions.json`). You should get a popup about this when you open the project. These extensions are also highlighted in the extensions list.

The following extensions are required or strongly recommended:

* [PHP Intelephense](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client)
* [phpcs](https://marketplace.visualstudio.com/items?itemName=ikappas.phpcs)
* [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug)
* [EditorConfig for VSCode](https://marketplace.visualstudio.com/items?itemName=editorconfig.editorconfig)
* [YAML](https://marketplace.visualstudio.com/items?itemName=redhat.vscode-yaml)
* [Docker](https://marketplace.visualstudio.com/items?itemName=ms-azuretools.vscode-docker)
* [GitLens](https://marketplace.visualstudio.com/items?itemName=eamodio.gitlens)
* [ShellCheck](https://marketplace.visualstudio.com/items?itemName=timonwong.shellcheck)
* [markdownlint](https://marketplace.visualstudio.com/items?itemName=DavidAnson.vscode-markdownlint)
* [phpstan](https://marketplace.visualstudio.com/items?itemName=SanderRonde.phpstan-vscode)
* [Psalm VScode Plugin](https://marketplace.visualstudio.com/items?itemName=getpsalm.psalm-vscode-plugin)
* [PHP Mess Detector](https://marketplace.visualstudio.com/items?itemName=ecodes.vscode-phpmd)
* [hadolint](https://marketplace.visualstudio.com/items?itemName=exiasr.hadolint)

## Production

* Set environment variables (e.g., `.env` file)
* Provide `FIREBASE_CONFIG_PATH` and `FIREBASE_BRANCH`. The firebase configuration file needs to be present in `FIREBASE_CONFIG_PATH`. In the firebase configuration file, `apiKey`, `authDomain`, `databaseURL` and `projectId` must be defined.
* Provide `crunz.yml` configuration settings for the cron

## Documentation

TBD

## Development

TBD

### Running scripts

There are several scripts that are run periodically in production.
These scripts are located in the directory `php/cron`/.

To test functionality that require these scripts, execute them manually as follows:

```shell
docker compose exec app php php/cron/<nameOfScript>.php
```

## Contributing

### Commit Message Format

*This specification is inspired by [Angular commit message format](https://github.com/angular/angular/blob/master/CONTRIBUTING.md#-commit-message-format)*.

We have very precise rules over how our Git commit messages must be formatted. It is based on the [Conventional Commits specification](https://www.conventionalcommits.org/en/v1.0.0/) which has the following advantages (non-exhaustive list):

* communicates the nature of changes to others
* allows a tool to automatically determine a version bump
* allows a tool to automatically generate the CHANGELOG

Each commit message consists of a **header**, a **body**, and a **footer**.

#### Commit Message Header

```text
<type>(<scope>): <short summary>
  │       │             │
  │       │             └─⫸ Summary in present tense. Not capitalized. No period at the end.
  │       │
  │       └─⫸ Commit Scope: deps|i18n
  │
  └─⫸ Commit Type: build|chore|ci|docs|feat|fix|perf|refactor|style|test
```

The `<type>` and `<summary>` fields are mandatory, the `(<scope>)` field is optional.

**Breaking Changes** must append a `!` after the type/scope.

##### Summary

Use the summary field to provide a succinct description of the change:

* use the imperative, present tense: "change" not "changed" nor "changes"
* don't capitalize the first letter
* no dot (.) at the end

##### Type

Must be one of the following:

* **build**: Changes that affect the build system or external dependencies (i.e., pip, Docker)
* **chore**: Other changes that don't modify source or test files (e.g., a grunt task)
* **ci**: Changes to our CI configuration files and scripts (i.e., GitLab CI)
* **docs**: Documentation only changes
* **feat**: A new feature
* **fix**: A bug fix
* **perf**: A code change that improves performance
* **refactor**: A code change that neither fixes a bug nor adds a feature
* **style**: Changes that do not affect the meaning of the code (whitespace, formatting etc.)
* **test**: Adding missing tests or correcting existing tests

##### Scope

The (optional) scope provides additional contextual information.

The following is the list of supported scopes:

* **deps**: Changes to the dependencies
* **i18n**: Changes to the translations (i18n)

#### Breaking Changes

In addition to appending a `!` after the type/scope in the commit message header, a breaking change must also be described in more detail in the commit message body prefixed with `BREAKING CHANGE:` (see [specification](https://www.conventionalcommits.org/en/v1.0.0/#commit-message-with-both--and-breaking-change-footer)).

## Environment-specific configuration

* put ssl certs in certs/ as server.crt and server.key
* ensure that the scripts in the `php/cron/` directory are executed periodically via a cronjob or similar
  * `endOfDayCheckout.php`: once per day at 23:30
  * `generateAppointmentReminders.php`: once per day at 18:00 (or whenever you want appointment reminders to be sent the day before)
  * `generateVwrAppointments.php`: every 3 seconds
  * `processIncomingSmsMessages.php`: every 5 seconds

## Open Issues

* Test the mailing setup in the DEV/QA servers, make sure the mailing service works properly
* Add CI/CD setup (`gitlab-ci.yml`)
* git hooks currently do not work since everything is done in the container and a multi-stage Dockerfile is used

## Profile system notes

* When adding a new column type, run the php/tool/verifyProfileColumns script right after

## Branding

This project includes Opal logos and other branding references.
To apply your own branding, search the repository globally for the key word `BRANDING`.
This will identify most instances of logos, names, etc. that should be replaced to reflect your brand identity.
Note: most of the branding elements can be configured by editing the `VirtualWaitingRoom/js/config.js` file.
Only two files must be updated manually: `VirtualWaitingRoom/js/vwr/templates/legendDialog.htm` and
`VirtualWaitingRoom/js/vwr/templates/legendDialogClinicalViewer.htm`.
