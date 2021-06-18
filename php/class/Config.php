<?php declare(strict_types = 1);

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
        public ?SmsConfig $sms,
        public ?DatabaseConfig $opalDb,
        public ?DatabaseConfig $questionnaireDb,
        public ?OpalConfig $opal,
        public ?AriaConfig $aria
    ) {}

    public static function getApplicationSettings(): Config
    {
        return self::$self;
    }

    static function __init(): void
    {
        $loadedData = parse_ini_file(__DIR__."/../../config/config.conf",TRUE) ?: throw new Exception("Loading configs failed");
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
            oieUrl:         $parsedData["path"]["OIE_URL"] ?? NULL,
            highchartsUrl:  $parsedData["path"]["HIGHCHARTS_URL"] ?? NULL
        );

        $system = new SystemConfig(
            emails:          $parsedData["alert"]["EMAIL"] ?? [],
            sendWeights:     (bool) ($parsedData["vwr"]["SEND_WEIGHTS"] ?? FALSE)
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
        if((bool) $parsedData["database"]["OPAL_DB_ENABLED"] !== TRUE) {
            $opalDb = NULL;
        }
        else {
            $opalDb = new DatabaseConfig(
                type:           $parsedData["database"]["OPAL_TYPE"],
                host:           $parsedData["database"]["OPAL_HOST"],
                port:           $parsedData["database"]["OPAL_PORT"],
                databaseName:   $parsedData["database"]["OPAL_DB"],
                username:       $parsedData["database"]["OPAL_USERNAME"],
                password:       $parsedData["database"]["OPAL_PASSWORD"],
            );
        }

        if((bool) $parsedData["database"]["QUESTIONNAIRE_DB_ENABLED"] !== TRUE) {
            $questionnaireDb = NULL;
        }
        else {
            $questionnaireDb = new DatabaseConfig(
                type:           $parsedData["database"]["QUESTIONNAIRE_TYPE"],
                host:           $parsedData["database"]["QUESTIONNAIRE_HOST"],
                port:           $parsedData["database"]["QUESTIONNAIRE_PORT"],
                databaseName:   $parsedData["database"]["QUESTIONNAIRE_DB"],
                username:       $parsedData["database"]["QUESTIONNAIRE_USERNAME"],
                password:       $parsedData["database"]["QUESTIONNAIRE_PASSWORD"],
            );
        }

        if((bool) $parsedData["sms"]["ENABLED"] !== TRUE) {
            $sms = NULL;
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

        if((bool) $parsedData["opal"]["ENABLED"] !== TRUE) {
            $opal = NULL;
        }
        else {
            $opal = new OpalConfig(
                opalAdminUrl:       $parsedData["opal"]["OPAL_ADMIN_URL"],
                opalAdminUsername:  $parsedData["opal"]["OPAL_ADMIN_USERNAME"],
                opalAdminPassword: $parsedData["opal"]["OPAL_ADMIN_PASSWORD"]
            );
        }

        if((bool) $parsedData["aria"]["ENABLED"] !== TRUE) {
            $aria = NULL;
        }
        else {
            $aria = new AriaConfig(
                checkInUrl: $parsedData["aria"]["ARIA_CHECKIN_URL"],
                photoUrl:   $parsedData["aria"]["PHOTO_URL"]
            );
        }

        self::$self = new self(
            environment:        $environment,
            system:             $system,
            ormsDb:             $ormsDb,
            logDb:              $logDb,
            opalDb:             $opalDb,
            questionnaireDb:    $questionnaireDb,
            sms:                $sms,
            opal:               $opal,
            aria:               $aria
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
            $val = ($val !== "") ? $val : NULL;
        }

        return $arr;
    }
}

/** @psalm-immutable */
class EnvironmentConfig
{
    function __construct(
        public string $basePath,
        public string $baseUrl,
        public string $imagePath,
        public string $imageUrl,
        public string $logPath,
        public string $firebaseUrl,
        public string $firebaseSecret,
        public ?string $oieUrl,
        public ?string $highchartsUrl
    ) {}
}

/** @psalm-immutable */
class DatabaseConfig
{
    function __construct(
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

    function __construct(
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

    function __construct(
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
    function __construct(
        public string $opalAdminUrl,
        public string $opalAdminUsername,
        public string $opalAdminPassword
    ) {}
}

/** @psalm-immutable */
class AriaConfig
{
    function __construct(
        public string $checkInUrl,
        public string $photoUrl //not being used in php, only perl
    ) {}
}
