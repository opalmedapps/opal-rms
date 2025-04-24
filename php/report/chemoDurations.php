<?php

// SPDX-FileCopyrightText: Copyright (C) 2019 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

// legacy report script refactored from perl

//---------------------------------------------------------------------------------------------------------------
// This script finds all the chemo appointments in specified date range and calculates the time spent inside the first "TX AREA" room the patient was checked in to.
//---------------------------------------------------------------------------------------------------------------

require __DIR__ ."/../../vendor/autoload.php";

use Orms\DataAccess\ReportAccess;
use Orms\DateTime;
use Orms\Http;

$params = Http::getRequestContents();

$sDateInit = $params["sDate"];
$eDateInit = $params["eDate"];

$sDate = DateTime::createFromFormatN("Y-m-d", $sDateInit)?->modifyN("midnight") ?? throw new Exception("Invalid date");
$eDate = DateTime::createFromFormatN("Y-m-d", $eDateInit)?->modifyN("tomorrow") ?? throw new Exception("Invalid date");

echo json_encode(ReportAccess::getChemoAppointments($sDate, $eDate));
