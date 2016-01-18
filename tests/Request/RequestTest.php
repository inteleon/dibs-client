<?php

namespace Inteleon\Dibs\Tests;

use Mockery;
use Inteleon\Dibs\Request\CurlRequest;
use PHPUnit_Framework_TestCase as TestSuite;

class RequestTest extends TestSuite
{
    /**
     * @var Inteleon\Dibs\Request\CurlRequest
     */
    protected $client;

    public function setUp()
    {
        parent::setUp();
        $this->client = new TestRequest(array());
    }

    /**
     * @test
     */
    public function checkURLisSet()
    {
        $url = 'http://httpbin.org/post';
        $this->client->to($url);
        $this->assertEquals($this->client->url, $url);
    }

    /**
     * @test
     *
     */
    public function postRequest()
    {
        $url = 'http://httpbin.org/post';
        $body = array(
            'foo'   => 'bar'
        );
        $result = $this->client->to($url)->post($body)->get();
        $this->assertEquals($body, $result['form']);
    }
}

class TestRequest extends CurlRequest
{
    public function get()
    {
        return json_decode($this->response, true);
    }
}
