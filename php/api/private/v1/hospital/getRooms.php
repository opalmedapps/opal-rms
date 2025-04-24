<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);
//====================================================================================
// php code to query the database and
// extract the list of options, which includes clinics and rooms
//====================================================================================

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Hospital\HospitalInterface;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$clinicHub  = (int) $params["clinicHub"];

$rooms = array_map(fn($x) => [
    "Name"               => $x["name"],
    "Type"               => $x["type"],
    "ScreenDisplayName"  => $x["screenDisplayName"],
    "VenueEN"            => $x["venueEN"],
    "VenueFR"            => $x["venueFR"],
],HospitalInterface::getRooms($clinicHub));

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($rooms));
