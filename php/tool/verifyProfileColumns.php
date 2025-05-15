<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

//run this script when adding or removing a new profile column database in the vwr
//the script will remove all columns in the ProfileColumns table that do not exist in the ProfileColumnsDescription table
//also inserts any missing columns for the profiles

require_once __DIR__ ."/../../vendor/autoload.php";

use Orms\DataAccess\Database;

$dbh = Database::getOrmsConnection();

//delete all non-existent columns
$dbh->query("
    DELETE FROM ProfileColumns
    WHERE ProfileColumnDefinitionSer NOT IN (
        SELECT
            ProfileColumnDefinitionSer
        FROM
            ProfileColumnDefinition
    )
");

//insert any missing columns
$dbh->query("
    INSERT INTO ProfileColumns(ProfileSer,ProfileColumnDefinitionSer)
    SELECT DISTINCT
        P.ProfileSer,
        PCD.ProfileColumnDefinitionSer
    FROM
        Profile P
        INNER JOIN ProfileColumnDefinition PCD ON PCD.ProfileColumnDefinitionSer NOT IN (
            SELECT
                PC.ProfileColumnDefinitionSer
            FROM
                ProfileColumns PC
            WHERE
                PC.ProfileSer = P.ProfileSer
        )
");

//check if there are profiles with no profile columns, and if there are, insert columns for the profile
$dbh->query("
    INSERT INTO ProfileColumns(ProfileSer,ProfileColumnDefinitionSer)
    SELECT
        P.ProfileSer,
        PCD.ProfileColumnDefinitionSer
    FROM
        Profile P,
        ProfileColumnDefinition PCD
    WHERE
        P.ProfileSer NOT IN (SELECT PC.ProfileSer FROM ProfileColumns PC)
");
