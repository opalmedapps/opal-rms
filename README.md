
<<path>> is the absolute path to the root of the project

To enable git hooks:
    sh .githooks/activateHooks.sh

Profile notes:
    -When adding a new column type, run the php/tool/verifyProfileColumns script right after

Highcharts:
    To start the highcharts export server (after running npm install):
        pm2 start node_modules/highcharts-export-server/bin/cli.js --name hes -- --enableServer 1 --sslOnly 1 --sslPort 7801 --sslPath ./certs

Cronjobs:
    create of copy of config/crunz.yml.template called config/crunz.yml
    set the error file location, mailer settings, and timezone (important, else the cron won't work)
    put in crontab: * * * * * cd <<path>>/config && ../vendor/bin/crunz schedule:run >/dev/null

httpd settings:
    create the file /etc/httpd/hardwareIpList.list and add the following:

        <RequireAny>
            Require ip x1
            Require ip x2

            Require valid-user
        </RequireAny>

    edit /etc/httpd/conf.d/ssl.conf and comment out the default VirtualHost directive. Then add the following:

        #redirect all http traffic to https
        <VirtualHost _default_:80>
            RewriteEngine On
            RewriteCond %{HTTPS} off
            RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI}
        </VirtualHost>

        <VirtualHost _default_:443>
            DocumentRoot "<<path>>"
            ErrorLog logs/ssl_error_log
            TransferLog logs/ssl_access_log
            LogLevel warn
            SSLEngine on
            SSLHonorCipherOrder on
            SSLCipherSuite PROFILE=SYSTEM
            SSLProxyCipherSuite PROFILE=SYSTEM
            SSLCertificateFile <<path>>/certs/server.crt
            SSLCertificateKeyFile <<path>>/certs/server.key

            <Directory "<<path>>">
                AllowOverride All
            </Directory>

            #allow users to see the login page
            <DirectoryMatch "<<path>>/(auth|images|node_modules)">
                Satisfy Any
            </DirectoryMatch>

            #give access to the public api
            <Directory "<<path>>/php/api/public">
                Satisfy Any
            </Directory>

            #allow kiosks to access kiosk web page
            <Directory "<<path>>/perl">
                <Files "CheckIn.pl">
                    #Include hardwareIpList.list
                </Files>
            </Directory>

            #allow tvs to access tv web page

            <DirectoryMatch "<<path>>/VirtualWaitingRoom/(css|images|sounds)">
                #Include hardwareIpList.list
            </DirectoryMatch>

            <Directory "<<path>>/VirtualWaitingRoom">
                <FilesMatch "screenDisplay.html|screenDisplay.js|module.js">
                    #Include hardwareIpList.list
                </FilesMatch>
            </Directory>

            <Directory "<<path>>/php/api/private/v1/vwr/">
                <FilesMatch "getFirebaseSettings">
                    #Include hardwareIpList.list
                </FilesMatch>
            </Directory>

        </VirtualHost>
