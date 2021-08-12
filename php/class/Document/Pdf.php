<?php

declare(strict_types=1);

namespace Orms\Document;

use Exception;

use Orms\Config;

class Pdf
{
    /**
     * Returns a base64 encoded pdf string generated from a latex string
     */
    public static function generatePdfStringFromLatexString(string $latexString): string
    {
        $outputDir = Config::getApplicationSettings()->environment->basePath ."/tmp";
        $filename = uniqid((string) rand());

        $fullFilePath = "$outputDir/$filename";

        //save the latex string to a .tex file
        file_put_contents("$fullFilePath.tex", $latexString);

        //pdflatex cannot write to absolute paths...
        /** @psalm-suppress ForbiddenCode */
        shell_exec("cd $outputDir && latexmk -pdf $fullFilePath.tex 2>&1") ?: "";

        //make sure the pdf file exists
        if(!file_exists("$fullFilePath.pdf")) throw new Exception("Failed to create pdf file");

        $pdfData = file_get_contents("$fullFilePath.pdf") ?: throw new Exception("Unable to open pdf file");
        $base64String = base64_encode($pdfData);

        //cleanup
        /** @psalm-suppress ForbiddenCode */
        shell_exec("cd $outputDir && latexmk -pdf -c -quiet");
        if(file_exists("$fullFilePath.tex")) unlink("$fullFilePath.tex");
        if(file_exists("$fullFilePath.pdf")) unlink("$fullFilePath.pdf");

        return $base64String;
    }
}
