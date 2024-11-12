<?php

declare(strict_types=1);

namespace Orms\External\OIE;

use DateTime;
use Orms\Config;
use Orms\Document\Measurement\Generator;
use Orms\External\OIE\Internal\Connection;
use Orms\Patient\Model\Patient;

class Export
{
    public static function exportMeasurementPdf(Patient $patient): void
    {
        if(Config::getApplicationSettings()->system->sendWeights !== true) return;

        //measurement document only suported by the RVH site for now
        $mrn = array_values(array_filter($patient->getActiveMrns(), fn($x) => $x->site === "RVH"))[0]->mrn ?? throw new \Exception("No RVH mrn");
        $site = array_values(array_filter($patient->getActiveMrns(), fn($x) => $x->site === "RVH"))[0]->site ?? throw new \Exception("No RVH mrn");

        Connection::getHttpClient()?->request("POST", Connection::API_MEASUREMENT_PDF, [
            "json" => [
                "mrn"             => $mrn,
                "site"            => $site,
                "reportContent"   => Generator::generatePdfString($patient),
                "docType"         => "ORMS Measurement",
                "documentDate"    => (new DateTime())->format("Y-m-d H:i:s"),
                "destination"     => "Streamline"
            ]
        ]);
    }
}
