<?php declare(strict_types=1);

namespace Orms;

use Exception;

class ApplicationException extends Exception
{
    const UNKNOWN_INSURANCE_TYPE = 1;
    const INVALID_INSURANCE_FORMAT = 2;
    const UNKNOWN_MRN_TYPE = 3;
    const INVALID_MRN_FORMAT = 4;

    function __construct(int $errorCode,string $errorMessage)
    {
        $message = self::getConstantString($errorCode) .": $errorMessage";

        parent::__construct($message);
    }

    private static function getConstantString(int $code): string
    {
        $class = new \ReflectionClass(__CLASS__);
        $constants = array_flip($class->getConstants());

        return $constants[$code];
    }
}
