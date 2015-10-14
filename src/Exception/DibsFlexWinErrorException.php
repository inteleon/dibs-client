<?php

namespace Inteleon\Dibs\Exception;

class DibsFlexWinErrorException extends DibsErrorException
{
    public function __construct($code, $previous = null)
    {
        if (!is_integer($code)) {
            $message = $code;
            $code = 1;
        }
        if (array_key_exists($code, self::$errors_payment_handling)) {
            $message = self::$errors_payment_handling[$code];
        }
        parent::__construct($message, $code, $previous);
    }
}
