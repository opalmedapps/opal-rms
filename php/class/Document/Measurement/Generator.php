<?php

// SPDX-FileCopyrightText: Copyright (C) 2021 Opal Health Informatics Group at the Research Institute of the McGill University Health Centre <john.kildea@mcgill.ca>
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Orms\Document\Measurement;

use Exception;

use Orms\Patient\Model\Patient;
use Orms\Patient\PatientInterface;

class Generator
{
    /**
     * loads a json graph template and returns a php array equivalent filled with the patient's measurements
     * @return mixed[]
     */
    public static function generateChartArray(Patient $patient, bool $weightsOnly = false): array
    {
        //get the chart template
        $chart = json_decode(file_get_contents(__DIR__ ."/graphTemplate.json") ?: "{}", true);

        //underscores are to get psalm to stop complaining about unused variables
        $_weightSeries = &$chart["series"][0];
        $_heightSeries = &$chart["series"][1];
        $_bsaSeries    = &$chart["series"][2];

        $measurements = PatientInterface::getPatientMeasurements($patient);

        foreach($measurements as $measurement)
        {
            foreach([&$_weightSeries,&$_heightSeries,&$_bsaSeries] as $index => &$series)
            {
                $value = $measurement->weight;
                if($index === 1) $value = $measurement->height;
                elseif($index === 2) $value = $measurement->bsa;

                //if the patient mrn stored with the measurement does not match the patient mrn, set the dot color to red to alert the chart user
                $series["data"][] = [
                    "x"     => $measurement->datetime->getTimestamp() *1000,
                    "y"     => $value,
                    "color" => in_array($measurement->mrnSite, array_map(fn($x) => $x->mrn ."-". $x->site, $patient->mrns)) ? $series["color"] : "red",
                ];

            }
        }

        if($weightsOnly === true) {
            $chart["series"] = [$chart["series"][0]];
        }

        return $chart;
    }

}
