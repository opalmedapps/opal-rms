<?php declare(strict_types = 1);

namespace Orms\Document\Measurement;

use Exception;

use Orms\Config;
use Orms\DateTime;
use Orms\Patient\Model\Patient;
use Orms\Patient\PatientInterface;
use Orms\Document\Pdf;
Use Orms\Document\Highcharts;

class Generator
{
    /**
     * loads a json highcharts template and returns a php array equivalent filled with the patient's measurements
     * @return mixed[]
     */
    static function generateChartArray(Patient $patient,bool $weightsOnly = FALSE): array
    {
        //get the chart template
        $chart = json_decode(file_get_contents(__DIR__ ."/highchartsTemplate.json") ?: "{}",TRUE);

        $weightSeries = &$chart["series"][0];
        $heightSeries = &$chart["series"][1];
        $bsaSeries    = &$chart["series"][2];

        $measurements = PatientInterface::getPatientMeasurements($patient);

        foreach($measurements as $measurement)
        {
            foreach([&$weightSeries,&$heightSeries,&$bsaSeries] as $index => &$series)
            {
                $value = $measurement->weight;
                if($index === 1) $value = $measurement->height;
                elseif($index === 2) $value = $measurement->bsa;

                #if the patient mrn stored with the measurement does not match the patient mrn, set the dot color to red to alert the chart user
                $series["data"][] = [
                    "x"     => $measurement->datetime->getTimestamp() *1000,
                    "y"     => $value,
                    "color" => in_array($measurement->mrnSite,array_map(fn($x) => $x->mrn ."-". $x->site,$patient->mrns)) ? $series["color"] : "red",
                ];

            }
        }

        if(($weightsOnly === TRUE)) $chart["series"] = [$chart["series"][0]];

        return $chart;
    }

    static function generatePdfString(Patient $patient): string
    {
        //generate a highcharts image and save it locally
        $chart = self::generateChartArray($patient,TRUE);

        //translate sections of the graph to french
        $chart["title"]["text"] = "Mesures Historique";
        $chart["series"][0]["name"] = "Poids (kg)";

        $imageStr = Highcharts::generateImageDataFromChart($chart);
        $imagePath = Config::getApplicationSettings()->environment->basePath ."/tmp/". uniqid((string) rand()) .".png";

        try {
            file_put_contents($imagePath,base64_decode($imageStr)) ?: throw new Exception("Unable to create image file!");
            $pdfStr = Pdf::generatePdfStringFromLatexString(self::_generateLatexString($patient,$imagePath));
        }
        catch(Exception $e) {
            throw $e;
        }
        finally {
            //delete the image created earlier
            if(file_exists($imagePath)) unlink($imagePath);
        }

        return $pdfStr;
    }

    private static function _generateLatexString(Patient $patient,string $chartImagePath): string
    {
        $measurements   = PatientInterface::getPatientMeasurements($patient);
        $fname          = $patient->firstName;
        $lname          = $patient->lastName;
        $mrn            = array_values(array_filter($patient->getActiveMrns(),fn($x) => $x->site === "RVH"))[0]->site ?? throw new Exception("No RVH mrn");
        $site           = "RV";
        $imagePath      = Config::getApplicationSettings()->environment->imagePath;

        //convert english month abreviation to french
        $now = str_replace(
            ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
            ["janv","févr","mars","avril","mai","juin","juil","août","sept","oct","nov","déc"],
            (new DateTime())->format("d M Y H:i")
        );

        $texString = "
            \\documentclass[12pt]{article}
            \\textheight=8.0in
            \\textwidth=8.0in
            \\topmargin=-1.0in
            \\raggedbottom
            \\oddsidemargin=-2.0cm
            \\evensidemargin=2.0cm
            \\usepackage{latexsym}
            \\usepackage[pdftex]{graphicx}
            \\usepackage{fancyhdr}
            \\usepackage{longtable}
            \\usepackage[table]{xcolor}
            \\usepackage{eso-pic}
            \\usepackage{afterpage}
            \\usepackage{lastpage}

            \\renewcommand{\\headheight}{1.0in}
            \\renewcommand{\\headrulewidth}{2pt}
            \\renewcommand{\\footrulewidth}{1pt}
            \\setlength{\\headwidth}{\\textwidth}
            \\fancyhead[R]{\\large\\textbf{Liste des poids et tailles du patient}}
            \\fancyhead[C]{\\includegraphics[height=0.53in]{{$imagePath}/logo.png}\\\\}

            \\fancyfoot[R]{Page \\thepage~de \\pageref{LastPage}} %page number on right
            \\fancyfoot[C]{} %blank central footer

            \\lfoot{\\textbf{FMU-4183 Source :} ARIA(REV 2018/08/24) \\\\
            \\hfill\\\\
            \\footnotesize{Si une version papier de ce document est re\\c{c}ue aux archives, avec ou sans notes manuscrites, en statut pr\\'{e}liminaire ou final, \\textbf{il ne sera pas num\\'{e}ris\\'{e}}.  Les corrections doivent \\^{e}tre faites dans le document pr\\'{e}liminaire ou via l'addendum si le document est final.\\\\
            \\hfill\\\\
            If a printout of this document is received in Medical Records, with or without handwritten notes, whether it is preliminary or final, \\textbf{it will not be scanned}.  Corrections must be done in the preliminary document or via an addendum if the document is final.}
            } %document info on left
            \\pagestyle{fancy}

            \\definecolor{light-gray}{gray}{0.95}
            \\definecolor{dark-gray}{gray}{0.8}
            \\definecolor{light-blue}{rgb}{0,0,0.99}
            \\definecolor{babyblueeyes}{rgb}{0.63, 0.79, 0.95}

            \\begin{document}
            \\begin{minipage}{0.65\\textwidth}
            \\hspace{-0.22in} Derni\\`{e}re mise \\`{a} jour : \\textbf{\\color{black}{{$now}}}\\\\
            \\bigskip

            \\vspace{-0.22in}
            \\hspace{-0.22in} Ce document est mis \\`{a} jour automatiquement \\`{a} la suite de la mise \\`{a} jour du poids ou de le taille du patient dans le syst\\`{e}me ORMS-Aria.
            \\end{minipage}
            \\begin{minipage}{0.25\\textwidth}
            \\begin{flushright}
            \\includegraphics[height=0.8in]{{$imagePath}/noscan.png}
            \\end{flushright}
            \\end{minipage}

            \\begin{center}
            \\textbf{{$lname}, {$fname} ($site-$mrn)}
            \\newline
            \\includegraphics[height=4.3in]{{$chartImagePath}}
            \\end{center}
            \\vspace{-0.3in}

            \\normalsize
            \\rowcolors{1}{light-gray}{dark-gray}
            \\renewcommand{\\arraystretch}{1.5}%row padding
            \\hfill\\\\
            \\hfill\\\\
            \\hfill\\\\
            \\hfill\\\\
            \\noindent{\\textbf{\\color{red}The medical information provided by this application (Opal/ORMS) is provided as an
            information resource only, and should not be used or relied on for any final diagnostic,
            prescription or treatment purposes. You, the healthcare provider, must validate the
            information prior to making any clinical decision or prescribing any treatment.}}
            \\begin{longtable}
            {
                |p{0.2\\linewidth}
                |p{0.16\\linewidth}
                |p{0.155\\linewidth}
                |p{0.15\\linewidth}
                |p{0.15\\linewidth}
                |p{0.15\\linewidth}
                |
            }
            \\rowcolor{white}\\multicolumn{6}{l}{\\textbf{{$lname}, $fname ($site-$mrn)}}\\\\
            \\rowcolor{white}\\multicolumn{6}{l}{\\color{white}{ }}\\\\
            \\hline
            \\rowcolor{babyblueeyes}
                \\textbf{Date et heure de la mesure}
                &\\textbf{Poids}
                &\\textbf{Taille}
                &\\textbf{Surface \\newline Corporelle}
                &\\textbf{Num\\'{e}ro \\newline du RDV}\\\\
            \\hline
            \\endhead
            \\hline
            \\hline
        ";

        //loop through the measurements and fill the table
        //table should be in reverse chronological order
        foreach($measurements as $index => $m)
        {
            $datetime = $m->datetime->format("Y-m-d H:i");

            //bold the first row
            if($index === 0)
            {
                $texString .= "
                    \\textbf{{$datetime}} & \\textbf{{$m->weight} kg} & \\textbf{{$m->height} cm} & \\textbf{{$m->bsa} m\\textsuperscript{2}} & \\textbf{{$m->appointmentId}} \\\\";
            }
            else
            {
                $texString .= "
                    {$datetime} & {$m->weight} kg & {$m->height} cm & {$m->bsa} m\\textsuperscript{2} & {$m->appointmentId} \\\\";
            }

            $texString .= "\\hline";
        }

        $texString .= "
            \\end{longtable}
            \\end{document}";

        return $texString;
    }

}
