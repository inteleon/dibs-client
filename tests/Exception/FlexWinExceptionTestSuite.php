<?php

namespace Inteleon\Dibs\Tests;

use Exception;
use PHPUnit_Framework_TestCase as TestSuite;

abstract class FlexWinExceptionTestSuite extends TestSuite
{
    /**
     * Exception to test
     *
     * @var Exception
     */
    protected $exception;

    abstract public function dibsMessageProvider();

    public function test_one_argument()
    {
        $message = "aa";
        $e = new $this->exception("aa");
        $expression = '/(\['.$message.'\])|('.$message.')/';
        $this->assertRegExp(
            $expression,
            $e->getMessage()
        );
        $this->assertEquals(0, $e->getCode());
    }

    /**
     * @dataProvider dibsMessageProvider
     *
     * @param  string $message Dibs message
     * @param  string $code    Dibs error code
     *
     */
    public function test_one_argument_dibs_message($message, $code)
    {
        $expression = '/(\['.$message.'\])|('.$message.')/';
        $e = new $this->exception($message, $code);

        $this->assertRegExp(
            $expression,
            $e->getMessage(),
            "Did not get expected message from code: $code"
        );
        $this->assertEquals($code, $e->getCode());
    }

    public function test_two_argument()
    {
        $e = new $this->exception("aa", 43);
        $this->assertEquals("aa", $e->getMessage());
        $this->assertEquals(43, $e->getCode());
    }

    public function test_three_argument()
    {
        $previous = new Exception("previous", 213);
        $e = new $this->exception("aa", 1, $previous);
        $this->assertRegExp(
            '/aa/',
            $e->getMessage()
        );
        $this->assertEquals(1, $e->getCode());
        $this->assertInstanceOf('Exception', $e->getPrevious());
    }
}
