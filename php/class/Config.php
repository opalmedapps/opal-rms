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
        public ?SmsConfig $sms,
        public ?OpalConfig $opal
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
            basePath:       $parsedData["path"]["BASE_PATH"],
            baseUrl:        $parsedData["path"]["BASE_URL"],
            imagePath:      $parsedData["path"]["IMAGE_PATH"],
            imageUrl:       $parsedData["path"]["IMAGE_URL"],
            logPath:        $parsedData["path"]["LOG_PATH"],
            firebaseUrl:    $parsedData["path"]["FIREBASE_URL"],
            firebaseSecret: $parsedData["path"]["FIREBASE_SECRET"],
            highchartsUrl:  $parsedData["path"]["HIGHCHARTS_URL"] ?? null
        );

        $system = new SystemConfig(
            emails:          $parsedData["system"]["EMAIL"] ?? [],
            sendWeights:     (bool) ($parsedData["system"]["SEND_WEIGHTS"] ?? false)
        );

        $ormsDb = new DatabaseConfig(
            type:           $parsedData["database"]["ORMS_TYPE"],
            host:           $parsedData["database"]["ORMS_HOST"],
            port:           $parsedData["database"]["ORMS_PORT"],
            databaseName:   $parsedData["database"]["ORMS_DB"],
            username:       $parsedData["database"]["ORMS_USERNAME"],
            password:       $parsedData["database"]["ORMS_PASSWORD"],
        );

        $logDb = new DatabaseConfig(
            type:           $parsedData["database"]["LOG_TYPE"],
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

        if((bool) $parsedData["opal"]["ENABLED"] !== true) {
            $opal = null;
        }
        else {
            $opal = new OpalConfig(
                opalAdminUrl:       $parsedData["opal"]["OPAL_ADMIN_URL"],
                opalAdminUsername:  $parsedData["opal"]["OPAL_ADMIN_USERNAME"],
                opalAdminPassword:  $parsedData["opal"]["OPAL_ADMIN_PASSWORD"]
            );
        }

        self::$self = new self(
            environment:        $environment,
            system:             $system,
            ormsDb:             $ormsDb,
            logDb:              $logDb,
            oie:                $oie,
            sms:                $sms,
            opal:               $opal
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
        public string $imageUrl,
        public string $logPath,
        public string $firebaseUrl,
        public string $firebaseSecret,
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
        public string $type,
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
        public bool $sendWeights
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

/** @psalm-immutable */
class OpalConfig
{
    public function __construct(
        public string $opalAdminUrl,
        public string $opalAdminUsername,
        public string $opalAdminPassword
    ) {}
}
