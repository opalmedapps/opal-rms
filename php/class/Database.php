<?php declare(strict_types = 1);

namespace Orms;

use PDO;

use Orms\Config;

//returns db connection handles to a requested database
class Database
{
    private static ?PDO $ormsConnection = NULL;
    private static ?PDO $logsConnection = NULL;
    private static ?PDO $opalConnection = NULL;
    private static ?PDO $questionnaireConnection = NULL;

    /** @var array<int,int> */
    private static array $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];

    static function getOrmsConnection(): PDO
    {
        if(self::$ormsConnection === NULL) {
            $dbInfo = Config::getApplicationSettings()->ormsDb;
            self::$ormsConnection = self::_getDatabaseConnection($dbInfo);
        }

        return self::$ormsConnection;
    }

    static function getLogsConnection(): PDO
    {
        if(self::$logsConnection === NULL) {
            $dbInfo = Config::getApplicationSettings()->logDb;
            self::$logsConnection = self::_getDatabaseConnection($dbInfo);
        }

        return self::$logsConnection;
    }

    static function getOpalConnection(): ?PDO
    {
        if(self::$opalConnection === NULL) {
            $dbInfo = Config::getApplicationSettings()->opalDb;
            self::$opalConnection = ($dbInfo === NULL) ? NULL : self::_getDatabaseConnection($dbInfo);
        }

        return self::$opalConnection;
    }

    static function getQuestionnaireConnection(): ?PDO
    {
        if(self::$questionnaireConnection === NULL) {
            $dbInfo = Config::getApplicationSettings()->questionnaireDb;
            self::$questionnaireConnection = ($dbInfo === NULL) ? NULL : self::_getDatabaseConnection($dbInfo);
        }

        return self::$questionnaireConnection;
    }

    private static function _getDatabaseConnection(DatabaseConfig $dbConf): PDO
    {
        return new PDO(
            self::_generateMysqlConnectionString($dbConf->host,$dbConf->port,$dbConf->databaseName),
            $dbConf->username,
            $dbConf->password,
            self::$pdoOptions
        );
    }

    private static function _generateMysqlConnectionString(string $host,string $port,string $dbName): string
    {
        return "mysql:host={$host};port={$port};dbname={$dbName}";
    }

}
