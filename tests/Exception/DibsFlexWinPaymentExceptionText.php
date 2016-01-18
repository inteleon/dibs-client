<?php

namespace Inteleon\Dibs\Tests;

use Mockery;
use Exception;
use Inteleon\Dibs\DibsFlexWin;
use Inteleon\Dibs\Exception\DibsErrorException;
use Inteleon\Dibs\Exception\DibsFlexWinPaymentException;

class DibsFlexWinPaymentExceptionText extends FlexWinExceptionTestSuite
{
    public function setUp()
    {
        parent::setUp();
        $this->exception = 'Inteleon\Dibs\Exception\DibsFlexWinPaymentException';
    }
    public function dibsMessageProvider()
    {
        $messages = array();
        array_walk(DibsErrorException::$errors_payment_handling, function ($message, $key) use (&$messages) {
            $msg = str_replace(array('(', ')', '[', ']'), '', $message);
            $messages[] = array($msg, $key);
        });
        return $messages;
    }
}
