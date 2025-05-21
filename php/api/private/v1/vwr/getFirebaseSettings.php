<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);
//script to get the parameters needed to connect to firebase

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Config;

$configs = Config::getApplicationSettings()->environment;

echo json_encode([
    "FirebaseConfig"       => $configs->firebaseConfig,
    "FirebaseBranch"    => $configs->firebaseBranch
]);
