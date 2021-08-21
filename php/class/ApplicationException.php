<?php

declare(strict_types=1);

namespace Orms;

use Exception;

class ApplicationException extends Exception
{
    public const UNKNOWN_INSURANCE_TYPE = "UNKNOWN_INSURANCE_TYPE";
    public const INVALID_INSURANCE_FORMAT = "INVALID_INSURANCE_FORMAT";
    public const UNKNOWN_MRN_TYPE = "UNKNOWN_MRN_TYPE";
    public const INVALID_MRN_FORMAT = "INVALID_MRN_FORMAT";
    public const NO_ACTIVE_MRNS = "NO_ACTIVE_MRNS";
    public const INVALID_SMS_APPOINTMENT_STATE = "INVALID_SMS_APPOINTMENT_STATE";
    public const INVALID_SMS_APPOINTMENT_TYPE ="INVALID_SMS_APPOINTMENT_TYPE";

    public function __construct(string $errorCode, string $errorMessage)
    {
        $message = self::getConstantString($errorCode) .": $errorMessage";

        parent::__construct($message);
    }

    private static function getConstantString(string $code): string
    {
        $class = new \ReflectionClass(__CLASS__);
        $constants = array_flip($class->getConstants());

        return $constants[$code];
    }
}
