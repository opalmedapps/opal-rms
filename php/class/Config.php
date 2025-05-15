<?php

declare(strict_types=1);

namespace Orms;

use Exception;

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
        $loadedData = parse_ini_file(__DIR__."/../../config/config.conf", true) ?: throw new Exception("Loading configs failed");
        $parsedData = self::_parseData($loadedData);

        //create required configs
        $environment = new EnvironmentConfig(
            basePath:                       $parsedData["path"]["BASE_PATH"],
            baseUrl:                        $parsedData["path"]["BASE_URL"],
            imagePath:                      $parsedData["path"]["IMAGE_PATH"],
            firebaseUrl:                    $parsedData["path"]["FIREBASE_URL"],
            firebaseSecret:                 $parsedData["path"]["FIREBASE_SECRET"],
            completedQuestionnairePath:     $parsedData["path"]["BASE_PATH"]."/tmp/completedQuestionnaires.json",
            highchartsUrl:                  $parsedData["path"]["HIGHCHARTS_URL"] ?? null,
        );

        $system = new SystemConfig(
            emails:                             $parsedData["system"]["EMAIL"] ?? [],
            sendWeights:                        (bool) ($parsedData["system"]["SEND_WEIGHTS"] ?? false),
            vwrAppointmentCronEnabled:          (bool) ($parsedData["system"]["VWR_CRON_ENABLED"] ?? false),
            appointmentReminderCronEnabled:     (bool) ($parsedData["system"]["SMS_REMINDER_CRON_ENABLED"] ?? false),
            processIncomingSmsCronEnabled:      (bool) ($parsedData["system"]["INCOMING_SMS_CRON_ENABLED"] ?? false),
            newOpalAdminUrl:                    $parsedData["opal"]["NEW_OPAL_ADMIN_URL"] ?? null,
            newOpalAdminToken:                  $parsedData["opal"]["NEW_OPAL_ADMIN_TOKEN"] ?? null,
        );

        $ormsDb = new DatabaseConfig(
            host:           $parsedData["database"]["ORMS_HOST"],
            port:           $parsedData["database"]["ORMS_PORT"],
            databaseName:   $parsedData["database"]["ORMS_DB"],
            username:       $parsedData["database"]["ORMS_USERNAME"],
            password:       $parsedData["database"]["ORMS_PASSWORD"],
        );

        $logDb = new DatabaseConfig(
            host:           $parsedData["database"]["LOG_HOST"],
            port:           $parsedData["database"]["LOG_PORT"],
            databaseName:   $parsedData["database"]["LOG_DB"],
            username:       $parsedData["database"]["LOG_USERNAME"],
            password:       $parsedData["database"]["LOG_PASSWORD"],
        );

        //create optional configs

        if((bool) $parsedData["oie"]["ENABLED"] !== true) {
            $oie = null;
        }
        else {
            $oie = new OpalInterfaceEngineConfig(
                oieUrl:    $parsedData["oie"]["OIE_URL"],
                username:  $parsedData["oie"]["OIE_USERNAME"],
                password:  $parsedData["oie"]["OIE_PASSWORD"]
            );
        }

        if((bool) $parsedData["sms"]["ENABLED"] !== true) {
            $sms = null;
        }
        else {
            $sms = new SmsConfig(
                provider:                       $parsedData["sms"]["PROVIDER"],
                licenceKey:                     $parsedData["sms"]["LICENCE_KEY"],
                token:                          $parsedData["sms"]["TOKEN"] ?? "",
                longCodes:                      $parsedData["sms"]["REGISTERED_LONG_CODES"] ?? [],
                failedCheckInMessageEnglish:    $parsedData["sms"]["FAILED_CHECK_IN_MESSAGE_EN"],
                failedCheckInMessageFrench:     $parsedData["sms"]["FAILED_CHECK_IN_MESSAGE_FR"],
                unknownCommandMessageEnglish:   $parsedData["sms"]["UNKNOWN_COMMAND_MESSAGE_EN"],
                unknownCommandMessageFrench:    $parsedData["sms"]["UNKNOWN_COMMAND_MESSAGE_FR"],
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
        public string $password
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
        public bool $processIncomingSmsCronEnabled
    ) {}
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
