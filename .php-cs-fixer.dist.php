<?php declare(strict_types=1);

use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\Token;

class DoubleQuoteFixer implements FixerInterface
{
    public function getName(): string
    {
        return "Orms/double_quote_fixer";
    }

    public function isRisky(): bool
    {
        return true;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function supports(SplFileInfo $file): bool
    {
        return true;
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition("aaa",[new CodeSample("'aaa' => \"aaa\"")]);
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_CONSTANT_ENCAPSED_STRING);
    }

    // foreach string that starts with single quotes, convert it to double quotes
    // ignore any strings that have single quotes inside of them
    public function fix(\SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token)
        {
            if (!$token->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                continue;
            }

            $content = $token->getContent();
            if (
                $content[0] === "'"
                && $content[strlen($content) -1] === "'"
                && str_contains($content,"\"") === false
            ) {
                $content = substr($content,1,-1);
                $content = "\"$content\"";

                $tokens[$index] = new Token($content);
            }
        }
    }
}

$config = new PhpCsFixer\Config();

return $config->registerCustomFixers([
    new DoubleQuoteFixer()
])
->setRules([
    "@PSR12:risky" => true,
    "braces" => false,
    "class_definition" => [], //currently, default @PSR12 doesn't work well with anonymous classes
    "declare_strict_types" => true,
    "dir_constant" => true,
    "function_declaration" => [
        "closure_function_spacing" => "none"
    ],
    "ordered_imports" => [
        "sort_algorithm" => "alpha"
    ],
    "single_line_comment_style" => [
        "comment_types" => ["asterisk","hash"]
    ],
    "strict_comparison" => true,
    "mb_str_functions" => true,
    "use_arrow_functions" => true,
    "Orms/double_quote_fixer" => true
])
->setRiskyAllowed(true)
->setCacheFile(__DIR__."/vendor/php-cs-fixer/.php-cs-fixer.cache")
->setFinder(PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude("vendor")
);
