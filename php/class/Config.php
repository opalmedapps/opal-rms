<?php

declare(strict_types=1);

namespace Orms;

use Exception;
use Dotenv\Dotenv;

Config::__init();

/** @psalm-immutable */
class Config
{
    private static Config $self;

    private function __construct(
        public EnvironmentConfig $environment,
        public SystemConfig $system,
        public DatabaseConfig $ormsDb,
        public DatabaseConfig $logDb,
        public ?OpalInterfaceEngineConfig $oie,
        public ?SmsConfig $sms
    ) {}

    public static function getApplicationSettings(): Config
    {
        return self::$self;
    }

    public static function __init(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
        // don't fail if the .env file is not there
        $dotenv->safeload();

        // Ensure that the following environment variables are set
        $dotenv->required('BASE_PATH')->notEmpty();
        $dotenv->required('BASE_URL')->notEmpty();
        $dotenv->required('IMAGE_PATH')->notEmpty();
        $dotenv->required('IMAGE_URL')->notEmpty();
        $dotenv->required('LOG_PATH')->notEmpty();
        $dotenv->required('ORMS_DATABASE_USER')->notEmpty();
        $dotenv->required('ORMS_DATABASE_PASSWORD')->notEmpty();
        $dotenv->required('ORMS_DATABASE_HOST')->notEmpty();
        $dotenv->required('ORMS_DATABASE_NAME')->notEmpty();
        $dotenv->required('ORMS_DATABASE_PORT')->isInteger();
        $dotenv->required('FIREBASE_URL')->notEmpty();
        $dotenv->required('FIREBASE_SECRET')->notEmpty();
        $dotenv->required('HIGHCHARTS_HOST')->notEmpty();
        $dotenv->required('HIGHCHARTS_PORT')->notEmpty();
        $dotenv->required('OIE_ENABLED')->notEmpty();
        $dotenv->required('SMS_ENABLED')->notEmpty();
        $dotenv->required('SMS_REMINDER_CRON_ENABLED')->notEmpty();
        $dotenv->required('SMS_INCOMING_SMS_CRON_ENABLED')->notEmpty();
        $dotenv->required('NEW_OPAL_ADMIN_URL')->notEmpty();
        $dotenv->required('NEW_OPAL_ADMIN_TOKEN')->notEmpty();
        $dotenv->required('SEND_WEIGHTS')->notEmpty();
        $dotenv->required('VWR_CRON_ENABLED')->notEmpty();
        $dotenv->required('RECIPIENT_EMAILS')->notEmpty();
        $dotenv->required('DATABASE_USE_SSL')->isBoolean();
        $dotenv->required('SSL_CA')->notEmpty();

        $_ENV = self::_parseData($_ENV);

        //create required configs
        $environment = new EnvironmentConfig(
            basePath:                       $_ENV["BASE_PATH"],
            baseUrl:                        $_ENV["BASE_URL"],
            imagePath:                      $_ENV["IMAGE_PATH"],
            firebaseUrl:                    $_ENV["FIREBASE_URL"],
            firebaseSecret:                 $_ENV["FIREBASE_SECRET"],
            completedQuestionnairePath:     $_ENV["BASE_PATH"]."/tmp/completedQuestionnaires.json",
            highchartsUrl:                  $_ENV["HIGHCHARTS_HOST"] . ':' . $_ENV['HIGHCHARTS_PORT'],
        );

        $system = new SystemConfig(
            emails:                             explode(',', $_ENV["RECIPIENT_EMAILS"]),
            sendWeights:                        (bool) ($_ENV["SEND_WEIGHTS"] ?? false),
            vwrAppointmentCronEnabled:          (bool) ($_ENV["VWR_CRON_ENABLED"] ?? false),
            appointmentReminderCronEnabled:     (bool) ($_ENV["SMS_REMINDER_CRON_ENABLED"] ?? false),
            processIncomingSmsCronEnabled:      (bool) ($_ENV["SMS_INCOMING_SMS_CRON_ENABLED"] ?? false),
            newOpalAdminUrl:                    $_ENV["NEW_OPAL_ADMIN_URL"] ?? null,
            newOpalAdminToken:                  $_ENV["NEW_OPAL_ADMIN_TOKEN"] ?? null,
            emailHost:                          $_ENV["EMAIL_HOST"] ?? null,
            emailHostUser:                      $_ENV["EMAIL_USER"] ?? null,
            emailHostPassword:                  $_ENV["EMAIL_PASSWORD"] ?? null,
            emailSentFromAddress:               $_ENV["EMAIL_SENT_FROM_ADDRESS"] ?? null,
            emailPort:                          $_ENV["EMAIL_PORT"] ?? null,
        );

        $ormsDb = new DatabaseConfig(
            host:           $_ENV["ORMS_DATABASE_HOST"],
            port:           $_ENV["ORMS_DATABASE_PORT"],
            databaseName:   $_ENV["ORMS_DATABASE_NAME"],
            username:       $_ENV["ORMS_DATABASE_USER"],
            password:       $_ENV["ORMS_DATABASE_PASSWORD"],
            usessl:         (bool) ($_ENV["DATABASE_USE_SSL"] ?? false),
            sslca:          $_ENV["SSL_CA"],
        );

        $logDb = new DatabaseConfig(
            host:           $_ENV["LOG_DATABASE_HOST"],
            port:           $_ENV["LOG_DATABASE_PORT"],
            databaseName:   $_ENV["LOG_DATABASE_NAME"],
            username:       $_ENV["LOG_DATABASE_USER"],
            password:       $_ENV["LOG_DATABASE_PASSWORD"],
            usessl:         (bool) ($_ENV["DATABASE_USE_SSL"] ?? false),
            sslca:          $_ENV["SSL_CA"],
        );

        //create optional configs

        if((bool) $_ENV["OIE_ENABLED"] !== true) {
            $oie = null;
        }
        else {
            $oie = new OpalInterfaceEngineConfig(
                oieUrl:    $_ENV["OIE_URL"],
                username:  $_ENV["OIE_USERNAME"],
                password:  $_ENV["OIE_PASSWORD"]
            );
        }

        if((bool) $_ENV["SMS_ENABLED"] !== true) {
            $sms = null;
        }
        else {
            $sms = new SmsConfig(
                provider:                       $_ENV["SMS_PROVIDER"],
                licenceKey:                     $_ENV["SMS_LICENCE_KEY"],
                token:                          $_ENV["SMS_TOKEN"] ?? "",
                longCodes:                      explode(',', $_ENV["REGISTERED_LONG_CODES"]),
                failedCheckInMessageEnglish:    $_ENV["FAILED_CHECK_IN_MESSAGE_EN"],
                failedCheckInMessageFrench:     $_ENV["FAILED_CHECK_IN_MESSAGE_FR"],
                unknownCommandMessageEnglish:   $_ENV["UNKNOWN_COMMAND_MESSAGE_EN"],
                unknownCommandMessageFrench:    $_ENV["UNKNOWN_COMMAND_MESSAGE_FR"],
            );
        }

        self::$self = new self(
            environment:        $environment,
            system:             $system,
            ormsDb:             $ormsDb,
            logDb:              $logDb,
            oie:                $oie,
            sms:                $sms
        );
    }

    /**
     * Function to convert all empty strings in an assoc array into nulls
     * @param array<string|string[]> $arr
     * @return mixed[]
     */
    private static function _parseData(array $arr): array
    {
        foreach($arr as &$val)
        {
            $val = is_array($val) ? self::_parseData($val) : $val;
            $val = ($val !== "") ? $val : null;
        }

        return $arr;
    }
}

/** @psalm-immutable */
class EnvironmentConfig
{
    public function __construct(
        public string $basePath,
        public string $baseUrl,
        public string $imagePath,
        public string $firebaseUrl,
        public string $firebaseSecret,
        public string $completedQuestionnairePath,
        public ?string $highchartsUrl
    ) {}
}

/** @psalm-immutable */
class OpalInterfaceEngineConfig
{
    public function __construct(
        public string $oieUrl,
        public string $username,
        public string $password
    ) {}
}

/** @psalm-immutable */
class DatabaseConfig
{
    public function __construct(
        public string $host,
        public string $port,
        public string $databaseName,
        public string $username,
        public string $password,
        public bool $usessl,
        public string $sslca
    ) {}
}

/** @psalm-immutable */
class SystemConfig
{
    /** @param string[] $emails */

    public function __construct(
        public array $emails,
        public bool $sendWeights,
        public bool $vwrAppointmentCronEnabled,
        public bool $appointmentReminderCronEnabled,
        public bool $processIncomingSmsCronEnabled,
        public string $newOpalAdminUrl,
        public string $newOpalAdminToken,
        public string $emailHost,
        public string $emailHostUser,
        public string $emailHostPassword,
        public string $emailSentFromAddress,
        public string $emailPort,
    ) {}

    /**
     * Function to build wearables URL based on the given $opalUUID parameter
     * @param string|null $opalUUID
     * @return string|null
     */
    public function getWearablesURL(?string $opalUUID): ?string
    {
        if (empty($opalUUID)) return NULL;

        return $this->newOpalAdminUrl
        . '/health-data/'
        . $opalUUID
        . '/quantity-samples/';
    }
}

/** @psalm-immutable */
class SmsConfig
{
    /**
     * @param string[] $longCodes
     */

    public function __construct(
        public string $provider,
        public string $licenceKey,
        public string $token,
        public array $longCodes,
        public string $failedCheckInMessageEnglish,
        public string $failedCheckInMessageFrench,
        public string $unknownCommandMessageEnglish,
        public string $unknownCommandMessageFrench
    ) {}
}
