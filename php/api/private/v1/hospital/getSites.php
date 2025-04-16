<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require __DIR__."/../../../../../vendor/autoload.php";

use Orms\Hospital\HospitalInterface;
use Orms\Http;

$sites = array_map(fn($x) => [
    "HospitalCode" => $x["hospitalCode"],
    "HospitalName" => $x["hospitalName"],
],HospitalInterface::getHospitalSites());

Http::generateResponseJsonAndExit(200, data: $sites);
