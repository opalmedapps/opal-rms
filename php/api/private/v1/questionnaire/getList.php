<?php

// SPDX-FileCopyrightText: Copyright (C) 2020 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\External\LegacyOpalAdmin\Fetch;
use Orms\Http;

Http::generateResponseJsonAndExit(200, data: Fetch::getListOfQuestionnaires());
