<?php

namespace Inteleon\Dibs\Exception;

use Exception;

class DibsFlexWinAuthException extends DibsErrorException
{
    public function __construct()
    {
        $args = func_get_args();
        $previous = null;
        switch (count($args)) {
            case 2:
            case 3:
                $message = $args[0];
                $code = (int)$args[1];
                break;
            default:
                $message = $this->getFromCode($args[0]);
                $code = (int)$args[0];
                break;
        }
        foreach ($args as $key => $value) {
            if ($value instanceof Exception) {
                $previous = $value;
            }
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get dibs error message based on code
     *
     * @param  integer $code
     *
     * @return string
     */
    private function getFromCode($code)
    {
        if (array_key_exists($code, self::$errors_payment_authorization)) {
            return "[$code]: " . self::$errors_payment_authorization[$code];
        }
        return "[$code]";
    }
}
