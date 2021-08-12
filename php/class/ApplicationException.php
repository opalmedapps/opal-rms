<?php

declare(strict_types=1);

namespace Orms;

use Exception;

class ApplicationException extends Exception
{
    public const UNKNOWN_INSURANCE_TYPE = 1;
    public const INVALID_INSURANCE_FORMAT = 2;
    public const UNKNOWN_MRN_TYPE = 3;
    public const INVALID_MRN_FORMAT = 4;
    public const INVALID_SMS_APPOINTMENT_STATE = 5;

    public function __construct(int $errorCode, string $errorMessage)
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
