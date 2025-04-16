<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);
//-------------------------------------------------
// Returns a list of resources depending on the speciality specified
//-------------------------------------------------

require_once __DIR__."/../../../../../vendor/autoload.php";

use Orms\Appointment\AppointmentInterface;
use Orms\Http;
use Orms\Util\Encoding;

$params = Http::getRequestContents();

$speciality = (int) ($params["speciality"] ?? null);

$codes = AppointmentInterface::getClinicCodes($speciality);

Http::generateResponseJsonAndExit(200, data: Encoding::utf8_encode_recursive($codes));
