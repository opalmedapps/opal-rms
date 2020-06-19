# questionnaires-in-pdf

This project is a part of the ORMS system, which creates patient-reported outcome reports. Each report consists of charts based on a patient's completed questionnaires. Created PDF file is submitted to the OASIS system.

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

You will need to install:

* PHP (Minimum 7.4)
* MySQL (Minimum 5.6.38)
* QuestionnaireDB and its stored procedures
* Composer (tested on 1.4.3)
* highcharts-export-server (tested on 2.0.24)
* pdflatex (tested on pdfTeX 3.14159265-2.6-1.40.18)
* npm (tested on 6.13.0)
* Node.js (tested on 8.9.1)

####QuestionnaireDB
Import `questionnaireDB2019.sql` from `questionnaires-in-pdf/src/QIP/Resources/sql/` using phpMyAdmin or any other tool. Once the database is installed, execute `routinesRequiredForQuestionnaireV2_27042020.sql` and `getCompletedQuestionnairesList.sql` for importing stored procedures.

####Composer

To quickly install the Composer in the current directory, run the following script in your terminal:

```
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('sha384', 'composer-setup.php') === 'baf1608c33254d00611ac1705c1d9958c817a1a33bce370c0595974b342601bd80b92a3f46067da89e3b06bff421f182') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

After running the installer, you can run this to move composer.phar to a directory that is in your path, so you can access it globally.

```
mv composer.phar /usr/local/bin/composer
```

For more details look [here](https://getcomposer.org/download/) and [here](https://getcomposer.org/doc/00-intro.md#globally).

#### Node.js and npm
For installing Node.js and npm look [here](https://www.npmjs.com/get-npm) and [here](https://docs.npmjs.com/downloading-and-installing-node-js-and-npm).

####highcharts-export-server
First, make sure you have `node.js` installed and there is no pre-installed PhantomJS (legacy export server). If you use CentOS, make sure that the following packages are installed as well: `tar`, `bzip2`, `fontconfig`, `freetype-devel`, `fontconfig-devel`, `libstdc++`.

Install the export server by opening a terminal and typing:

```
npm install highcharts-export-server -g
```

To check if the package was installed correctly, run the following command:

```
highcharts-export-server --instr "{xAxis: {categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug','Sep', 'Oct', 'Nov', 'Dec']},series: [{data: [29.9, 71.5, 106.4, 129.2, 144.0, 176.0, 135.6, 148.5, 216.4, 194.1, 95.6, 54.4]}]}" --outfile 'image.png' -type 'png'
```

The command should return an image with the chart.

If you get an empty image or have issues during installing, read the next section.

####highcharts-export-server installing issues
If `highcharts-export-server` tool does not work, it is most likely due to improper installed packages by npm.

One of the solutions is the following steps:
1. Globally uninstall `highcharts-export-server`
    ```
    npm uninstall highcharts-export-server -g
    ```
2. Install `highcharts-export-server` avoiding install scripts, that fail
    ```
    npm install -g highcharts-export-server --ignore-scripts
    ```
3. run `node install.js` in `/path-to-global-NodeJS-moduels/highcharts-export-server/node_modules/phantomjs-prebuilt` folder
4. run `node build.js` in `/path-to-global-NodeJS-moduels/highcharts-export-server folder`. In this step, prompt questions will appear. You should leave the default answers and press `Enter`. If you manually write the answers, it can cause failed installation.

For more details read [here](https://github.com/highcharts/node-export-server/issues/161#issuecomment-417405024).

In case if this solution does not help, I have attached `questionnaires-in-pdf/highcharts-export-server` folder with all the needed modules. You can copy the folder to your global npm folder or use it straight away (`/questionnaires-in-pdf/highcharts-export-server/bin/cli.js`).

### Installing

Copy the root directory `questionnaires-in-pdf` to your running environment.

Run the following command for autoloading namespaces in PHP (PSR-4):
```
composer install
```

Install `Node.JS` dependencies (node modules) for the stress test:
```
npm update
```

## Usage

Start the `highcharts-export-server` for rendering chart images:

```
highcharts-export-server --enableServer 1 --queueSize 50 --logDest ./highcharts-export-server-log logFilele highcharts-export-server.log --logLevel 4 nologo true
```

It's recommended to run the server using `forever` (a simple CLI tool for ensuring that a given script runs continuously) unless running in a managed environment such as AWS Elastic Beanstalk. We agreed to use `pm2` for this purpose.
For more details about running in `forever` and `highcharts-export-server` parameters read [here](https://github.com/highcharts/node-export-server).

Please, read [how the server handles requests](https://github.com/highcharts/node-export-server#worker-count--work-limit).

To build a PDF file based on the test-patient data, run the following command:

```
php main.php
```

## Running the tests

To run the stress test on the `highcharts-export-server`:

```
node stress-test.js
```

It fires batches of 10 requests every 10ms, and expects the server to be running on port 8081.

This test can be useful for tuning `highcharts-export-server` parameters.

## Improving execution time

### Fetching data

To improve the data fetching time (stored procedure call), first of all, update `innodb_buffer_pool_size` in MySQL.
To do so, update the `innodb_buffer_pool_size` field in your my.cnf file:

```
[mysqld]
innodb_buffer_pool_size=128M
````

Note that you can set the buffer size up to 50 - 80 % of RAM but beware of setting memory usage too high.

Restart your mysql to make it effect.

### Compiling PDF

To speed up pdf compilation, use the format file with precompiled preamble. To generate the precompiled file, uncomment
the preamble in the `report.tex` file and run the next command:

```
pdftex -halt-on-error -output-directory=src/QIP/Resources/latex-markup -ini -jobname='report' "&pdflatex" mylatexformat.ltx src/QIP/Resources/latex-markup/report.tex
````

After successful generation of the `report.fmt`, comment out the preamble part in the `report.tex`.

For more information read [here](https://web.archive.org/web/20160712215709/http://www.howtotex.com:80/tips-tricks/faster-latex-part-iv-use-a-precompiled-preamble/), [here](http://web.archive.org/web/20170718172440/http://7fttallrussian.blogspot.it/2010/02/speed-up-latex-compilation.html), and [here](https://tex.stackexchange.com/a/377033).

## Built With

* [Composer](https://getcomposer.org) - A Dependency Manager for PHP
* [highcharts-export-server](https://github.com/highcharts/node-export-server) - Converts Highcharts.JS charts to static image files
* [pdflatex](https://linux.die.net/man/1/pdflatex) - Used to generate PDF files
* [npm](https://www.npmjs.com) - Node package manager
* [node](https://nodejs.org/en/) - JavaScript runtime environment

## Authors

* **Anton Gladyr** - *Initial work*

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details
