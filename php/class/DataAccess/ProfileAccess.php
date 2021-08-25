<?php

declare(strict_types=1);

namespace Orms\DataAccess;

use Orms\DataAccess\Database;

class ProfileAccess
{
    /**
     *
     * @return null|array{
     *  profileSer: int,
     *  profileId: string,
     *  category: string,
     *  specialityGroupId: int,
     * }
     */
    public static function getProfile(string $profileId): ?array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                ProfileSer,
                ProfileId,
                Category,
                SpecialityGroupId
            FROM
                Profile
            WHERE
                ProfileId = :proId
        ");
        $query->execute([
            ":proId" => $profileId,
        ]);

        $profile = $query->fetchAll()[0] ?? null;

        if($profile === null) {
            return null;
        }

        return [
            "profileSer"         => (int) $profile["ProfileSer"],
            "profileId"          => $profile["ProfileId"],
            "category"           => $profile["Category"],
            "specialityGroupId"  => (int) $profile["SpecialityGroupId"],
        ];
    }

    /**
     *
     * @return list<array{
     *  columnName: string,
     *  displayName: string,
     *  glyphicon: string,
     *  position: int,
     * }>
     */
    static function getProfileColumns(string $profileId): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                PCD.ColumnName,
                PCD.DisplayName,
                PCD.Glyphicon,
                PC.Position
            FROM
                ProfileColumns PC
                INNER JOIN ProfileColumnDefinition PCD ON PCD.ProfileColumnDefinitionSer = PC.ProfileColumnDefinitionSer
            WHERE
                PC.ProfileSer = ?
                AND PC.Position >= 0
                AND PC.Active = 1
            ORDER BY
                PC.Position
        ");
        $query->execute([self::_getProfileSer($profileId)]);

        return array_map(fn($x) => [
            "columnName"  => $x["ColumnName"],
            "displayName" => $x["DisplayName"],
            "glyphicon"   => $x["Glyphicon"],
            "position"    => (int) $x["Position"],
        ],$query->fetchAll());
    }

    /**
     *
     * @return list<array{
     *  name: string,
     *  type: string
     * }>
     */
    static function getProfileOptions(string $profileId): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                Options,
                Type
            FROM
                ProfileOptions
            WHERE
                ProfileSer = ?
            ORDER BY
                Options
        ");
        $query->execute([self::_getProfileSer($profileId)]);

        return array_map(fn($x) => [
            "name"  => $x["Options"],
            "type"  => $x["Type"],
        ],$query->fetchAll());
    }

    /**
     *
     * @return list<array{
     *  ProfileSer: int,
     *  ProfileId: string,
     *  Category: string,
     * }>
     */
    public static function getProfileList(?string $category,?int $specialityGroupId): array
    {
        $bindParams = [];
        $categoryFilter = "";
        $specialityFilter = "";

        if($category !== null) {
            $categoryFilter = "AND Category = :cat";
            $bindParams[":cat"] = $category;
        }

        if($specialityGroupId !== null) {
            $specialityFilter = "AND SpecialityGroupId = :spec";
            $bindParams[":spec"] = $specialityGroupId;
        }

        $query = Database::getOrmsConnection()->prepare("
            SELECT
                ProfileSer,
                ProfileId,
                CASE WHEN Category = 'PAB' THEN 'PAB/Clerical/Nursing' ELSE Category END AS Category
            FROM
                Profile
            WHERE
                1=1
                $categoryFilter
                $specialityFilter
            ORDER BY
                Category,
                ProfileId
        ");
        $query->execute($bindParams);

        return array_map(fn($x) => [
            "ProfileSer" => (int) $x["ProfileSer"],
            "ProfileId"  => $x["ProfileId"],
            "Category"   => $x["Category"]
        ],$query->fetchAll());
    }


    /**
     *
     * @return list<array{
     *  ColumnName: string,
     *  DisplayName: string,
     *  Glyphicon: string,
     *  Description: string,
     * }>
     */
    public static function getColumnDefinitions(): array
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                ColumnName,
                DisplayName,
                Glyphicon,
                Description
            FROM
                ProfileColumnDefinition
            ORDER BY
                ColumnName
        ");
        $query->execute();

        return array_map(fn($x) => [
            "ColumnName"    => $x["ColumnName"],
            "DisplayName"   => $x["DisplayName"],
            "Glyphicon"     => $x["Glyphicon"],
            "Description"   => $x["Description"],
        ],$query->fetchAll());
    }

    public static function createProfile(string $profileId,int $specialityGroupId): int
    {
        $dbh = Database::getOrmsConnection();

        //create the profile
        $dbh->prepare("INSERT INTO Profile (ProfileId,SpecialityGroupId) VALUES (?,?)")->execute([$profileId,$specialityGroupId]);

        $profileSer = (int) $dbh->lastInsertId();

        //attach every defined profile column to the new profile
        $dbh->prepare("
            INSERT INTO ProfileColumns(ProfileSer,ProfileColumnDefinitionSer)
            SELECT
                ?,
                ProfileColumnDefinitionSer
            FROM
                ProfileColumnDefinition
        ")->execute([$profileSer]);

        return $profileSer;
    }

    /**
     *
     * @param list<array{Name: string, Type: string}> $options
     * @param list<string> $columns
     */
    public static function updateProfile(int $profileSer,string $profileId,string $category,int $specialityGroupId,array $options,array $columns): void
    {
        $dbh = Database::getOrmsConnection();

        $dbh->prepare("
            UPDATE Profile
            SET
                ProfileId           = :profileId,
                Category            = :category,
                SpecialityGroupId   = :speciality
            WHERE
                ProfileSer = :profileSer
        ")->execute([
            ":profileId"     => $profileId,
            ":category"      => $category,
            ":speciality"    => $specialityGroupId,
            ":profileSer"    => $profileSer
        ]);

        //update the profile options

        //delete all current options for the profile
        $dbh->prepare("DELETE FROM ProfileOptions WHERE ProfileSer = ?")->execute([$profileSer]);

        $optionInsert = $dbh->prepare("
            INSERT INTO ProfileOptions(ProfileSer,Options,Type)
            VALUES(?,?,?)
        ");

        foreach($options as $opt) {
            $optionInsert->execute([$profileSer,$opt["Name"],$opt["Type"]]);
        }

        //update the profile columns

        //deactivate all columns for the profile
        $dbh->prepare("
            UPDATE ProfileColumns
            SET
                Position = -1,
                Active = 0
            WHERE
                ProfileSer = ?
        ")->execute([$profileSer]);

        //update the position and status for each column
        $columnInsert = $dbh->prepare("
            UPDATE ProfileColumns PC
            INNER JOIN ProfileColumnDefinition PCD ON PCD.ProfileColumnDefinitionSer = PC.ProfileColumnDefinitionSer
                AND PCD.ColumnName = :name
            SET
                PC.Position = :position,
                PC.Active = 1
            WHERE
                PC.ProfileSer = :pSer;
        ");

        foreach($columns as $index => $val) {
            $columnInsert->execute([
                ":pSer"     => $profileSer,
                ":position" => $index+1, //the position starts at 1 in the db
                "name"      => $val,
            ]);
        }
    }

    public static function deleteProfile(string $profileId): void
    {
        $profileSer = self::_getProfileSer($profileId);

        $dbh = Database::getOrmsConnection();

        //delete associated columns
        $dbh->prepare("DELETE FROM ProfileColumns WHERE ProfileSer = ?")->execute([$profileSer]);

        //delete associated options
        $dbh->prepare("DELETE FROM ProfileOptions WHERE ProfileSer = ?")->execute([$profileSer]);

        //finally, delete the profile
        $dbh->prepare("DELETE FROM Profile WHERE ProfileSer = ?")->execute([$profileSer]);
    }

    private static function _getProfileSer(string $profileId): int
    {
        $query = Database::getOrmsConnection()->prepare("
            SELECT
                ProfileSer
            FROM
                Profile
            WHERE
                ProfileId = ?
        ");
        $query->execute([$profileId]);

        return (int) ($query->fetchAll()[0]["ProfileSer"] ?? 0);
    }
}
