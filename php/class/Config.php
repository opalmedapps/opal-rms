<?php declare(strict_types = 1);

namespace Orms;

use Exception;
use TypeError;

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
        public ?AriaConfig $aria,
        public ?MuhcConfig $muhc,
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
            oieUrl:         $parsedData["path"]["OIE_URL"] ?? NULL,
            highchartsUrl:  $parsedData["path"]["HIGHCHARTS_URL"] ?? NULL
        );

        $system = new SystemConfig(
            emails:          $parsedData["alert"]["EMAIL"] ?? [],
            firebaseUrl:     $parsedData["vwr"]["FIREBASE_URL"],
            firebaseSecret:  $parsedData["vwr"]["FIREBASE_SECRET"],
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
        try {
            $opalDb = new DatabaseConfig(
                type:           $parsedData["database"]["OPAL_TYPE"],
                host:           $parsedData["database"]["OPAL_HOST"],
                port:           $parsedData["database"]["OPAL_PORT"],
                databaseName:   $parsedData["database"]["OPAL_DB"],
                username:       $parsedData["database"]["OPAL_USERNAME"],
                password:       $parsedData["database"]["OPAL_PASSWORD"],
            );
        } catch(TypeError) {$opalDb = NULL;}

        try {
            $questionnaireDb = new DatabaseConfig(
                type:           $parsedData["database"]["QUESTIONNAIRE_TYPE"],
                host:           $parsedData["database"]["QUESTIONNAIRE_HOST"],
                port:           $parsedData["database"]["QUESTIONNAIRE_PORT"],
                databaseName:   $parsedData["database"]["QUESTIONNAIRE_DB"],
                username:       $parsedData["database"]["QUESTIONNAIRE_USERNAME"],
                password:       $parsedData["database"]["QUESTIONNAIRE_PASSWORD"],
            );
        }
        catch(TypeError) {$questionnaireDb = NULL;}

        try {
            $sms = new SmsConfig(
                enabled:                        (bool) $parsedData["sms"]["ENABLED"],
                provider:                       $parsedData["sms"]["PROVIDER"],
                licenceKey:                     $parsedData["sms"]["LICENCE_KEY"],
                token:                          $parsedData["sms"]["TOKEN"] ?? "",
                longCodes:                      $parsedData["sms"]["REGISTERED_LONG_CODES"] ?? [],
                failedCheckInMessageEnglish:    $parsedData["sms"]["FAILED_CHECK_IN_MESSAGE_EN"],
                failedCheckInMessageFrench:     $parsedData["sms"]["FAILED_CHECK_IN_MESSAGE_FR"],
                unknownCommandMessageEnglish:   $parsedData["sms"]["UNKNOWN_COMMAND_MESSAGE_EN"],
                unknownCommandMessageFrench:    $parsedData["sms"]["UNKNOWN_COMMAND_MESSAGE_FR"],
            );
        } catch(TypeError) {$sms = NULL;}

        try {
            $opal = new OpalConfig(
                opalAdminUrl:       $parsedData["opal"]["OPAL_ADMIN_URL"]
            );
        } catch(TypeError) {$opal = NULL;}

        try {
            $aria = new AriaConfig(
                checkInUrl: $parsedData["aria"]["ARIA_CHECKIN_URL"],
            );
        } catch(TypeError) {$aria = NULL;}

        try {
            $muhc = new MuhcConfig(
                pdsUrl: $parsedData["muhc"]["PDS_URL"],
            );
        } catch(TypeError) {$muhc = NULL;}

        self::$self = new self(
            environment:        $environment,
            system:             $system,
            ormsDb:             $ormsDb,
            logDb:              $logDb,
            opalDb:             $opalDb,
            questionnaireDb:    $questionnaireDb,
            sms:                $sms,
            opal:               $opal,
            aria:               $aria,
            muhc:               $muhc,
        );
    }

    /**
     * Function to convert all empty string in an assoc array into nulls
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
        public string $firebaseUrl,
        public string $firebaseSecret,
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
        public bool $enabled,
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
        public string $opalAdminUrl
    ) {}
}

/** @psalm-immutable */
class MuhcConfig
{
    function __construct(
        public string $pdsUrl,
    ) {}
}

/** @psalm-immutable */
class AriaConfig
{
    function __construct(
        public string $checkInUrl,
        //public string photoUrl //not being used in php, only perl
    ) {}
}
