<?php declare(strict_types = 1);

namespace Orms;

use PDO;

use Orms\Config;

//returns db connection handles to a requested database
class Database
{
    /** @var array<int,int> */
    private static array $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];

    static function getOrmsConnection(): PDO
    {
        $dbInfo = Config::getApplicationSettings()->ormsDb;
        return self::_getDatabaseConnection($dbInfo);
    }

    static function getLogsConnection(): PDO
    {
        $dbInfo = Config::getApplicationSettings()->logDb;
        return self::_getDatabaseConnection($dbInfo);
    }

    static function getOpalConnection(): ?PDO
    {
        $dbInfo = Config::getApplicationSettings()->opalDb;
        return $dbInfo === NULL ? NULL : self::_getDatabaseConnection($dbInfo);
    }

    static function getQuestionnaireConnection(): ?PDO
    {
        $dbInfo = Config::getApplicationSettings()->questionnaireDb;
        return $dbInfo === NULL ? NULL : self::_getDatabaseConnection($dbInfo);
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

?>
