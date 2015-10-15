<?php

namespace Inteleon\Dibs\Exception;

class DibsFlexWinErrorException extends DibsErrorException
{
    public function __construct()
    {
        $args = func_get_args();
        switch (count($args)) {
            case 1:
                $message = $this->getFromCode($args[0]);
                $code = (int)$args[0];
                $previous = null;
                break;
            case 2:
                $message = $args[0];
                $code = (int)$args[1];
                $previous = $args[2];
                break;
            default:
                $message = $args[0];
                $code = (int)$args[0];
                $previous = null;
                break;
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
        if (array_key_exists($code, self::$errors_payment_handling)) {
            return "[$code]: " . self::$errors_payment_handling[$code];
        }
        return "[$code]";
    }
}
