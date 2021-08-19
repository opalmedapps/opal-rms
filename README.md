
To enable git hooks:
    sh .githooks/activateHooks.sh

Profile notes:
    -When creating or deleting profiles in the WaitRoomMangement DB, be sure to use the stored procedures (SetupProfile/DeleteProfile respectively).
    -When adding a new column type, run the VerifyProfileColumns procedure right after.

Highcharts:
    To start the highcharts export server (after running npm install):
        pm2 start node_modules/highcharts-export-server/bin/cli.js --name hes -- --enableServer 1

Cronjobs:
    create of copy of config/crunz.yml.template called config/crunz.yml
    set the error file location, mailer settings, and timezone (important, else the cron won't work)
    put in crontab: * * * * * cd /path/to/project/config && ../vendor/bin/crunz schedule:run

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
            DocumentRoot "/var/www/OnlineRoomManagementSystem"
            ErrorLog logs/ssl_error_log
            TransferLog logs/ssl_access_log
            LogLevel warn
            SSLEngine on
            SSLHonorCipherOrder on
            SSLCipherSuite PROFILE=SYSTEM
            SSLProxyCipherSuite PROFILE=SYSTEM
            SSLCertificateFile /etc/pki/tls/certs/localhost.crt
            SSLCertificateKeyFile /etc/pki/tls/private/localhost.key

            <Directory "/var/www/OnlineRoomManagementSystem">
                AllowOverride All
            </Directory>

            #allow users to see the login page
            <DirectoryMatch "/var/www/OnlineRoomManagementSystem/(auth|images|node_modules)">
                Satisfy Any
            </DirectoryMatch>

            #give access to the public api
            <Directory "/var/www/OnlineRoomManagementSystem/php/api/public">
                Satisfy Any
            </Directory>

            #allow kiosks to access kiosk web page
            <Directory "/var/www/OnlineRoomManagementSystem/perl">
                <Files "Checkin.pl">
                    #Include hardwareIpList.list
                </Files>
            </Directory>

            #allow tvs to access tv web page

            <DirectoryMatch "/var/www/OnlineroomManagementSystem/VirtualWaitingRoom/(css|images|sounds)">
                #Include hardwareIpList.list
            </DirectoryMatch>

            <Directory "/var/www/OnlineroomManagementSystem/VirtualWaitingRoom">
                <FilesMatch "screenDisplay.html|screenDisplay.js|module.js">
                    #Include hardwareIpList.list
                </FilesMatch>
            </Directory>

            <Directory "/var/www/OnlineRoomManagementSystem/php/private/v1/vwr/">
                <Files "getFirebaseSettings.php">
                    #Include hardwareIpList.list
                </Files>
            </Directory>

        </VirtualHost>
