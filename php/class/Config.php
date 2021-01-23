<?php declare(strict_types = 1);

namespace Orms;

use Exception;
use PDO;

Config::__init();

class Config
{

    /** @var mixed[] */
    private static array $configs;

    #class constructor
    public static function __init(): void
    {
        #load the config file
        $configs = parse_ini_file(dirname(__FILE__) ."/../../config/config.conf",TRUE);

        if($configs === FALSE) throw new Exception("Loading configs failed");

        self::$configs = $configs;
    }

    /**
     * returns a hash with all configs
     * @return mixed[]
     */
    public static function GetAllConfigs(): array
    {
        return self::$configs;
    }

    /**
     * returns a hash with specific configs
     * @return mixed[]
     */
    public static function getConfigs(string $section): array
    {
        return self::$configs[$section];
    }

    #returns a db connection handle to a requested database server
    #options are currently predefined as "ORMS"
    #return 0 if connection fails
    public static function getDatabaseConnection(string $requestedConnection): PDO
    {
        $dbInfo = self::$configs['database'];
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        #connects to WaitRoomManagment db by default
        if($requestedConnection === 'ORMS') {
            $dbh = new PDO("mysql:host={$dbInfo['ORMS_HOST']};port={$dbInfo['ORMS_PORT']};dbname={$dbInfo['ORMS_DB']}",$dbInfo['ORMS_USERNAME'],$dbInfo['ORMS_PASSWORD'],$options);
        }

        #logging db
        elseif($requestedConnection === 'LOGS') {
            $dbh = new PDO("mysql:host={$dbInfo['LOG_HOST']};port={$dbInfo['LOG_PORT']};dbname={$dbInfo['LOG_DB']}",$dbInfo['LOG_USERNAME'],$dbInfo['LOG_PASSWORD'],$options);
        }

        #opal db
        elseif($requestedConnection === 'OPAL') {
            $dbh = new PDO("mysql:host={$dbInfo['OPAL_HOST']};port={$dbInfo['OPAL_PORT']};dbname={$dbInfo['OPAL_DB']}",$dbInfo['OPAL_USERNAME'],$dbInfo['OPAL_PASSWORD'],$options);
        }

        #questionnaire db
        elseif($requestedConnection === 'QUESTIONNAIRE') {
            $dbh = new PDO("mysql:host={$dbInfo['QUESTIONNAIRE_HOST']};port={$dbInfo['QUESTIONNAIRE_PORT']};dbname={$dbInfo['QUESTIONNAIRE_DB']}",$dbInfo['QUESTIONNAIRE_USERNAME'],$dbInfo['QUESTIONNAIRE_PASSWORD'],$options);
        }

        else {
            throw new Exception("Couldn't connect to database");
        }

        return $dbh;
    }
}

?>
