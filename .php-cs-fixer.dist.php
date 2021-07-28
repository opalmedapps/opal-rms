<?php declare(strict_types=1);

$config = new PhpCsFixer\Config();

return $config->setRules([
    //"@PSR12" => true,
    // "visibility_required" => [],
    "declare_strict_types" => true,
    "strict_comparison" => true,
    // "function_declaration" => [
    //     "closure_function_spacing" => "none"
    // ],
    // "mb_str_functions" => true,
    "dir_constant" => true
])
->setRiskyAllowed(true)
->setCacheFile(__DIR__."/vendor/php-cs-fixer/.php-cs-fixer.cache")
->setFinder(PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude("vendor")
    ->exclude("VirtualWaitingRoom/php/pdfGenerator")
);
