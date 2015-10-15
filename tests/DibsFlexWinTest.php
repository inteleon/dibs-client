<?php

namespace Inteleon\Dibs\Tests;

use Mockery;
use Inteleon\Dibs\DibsFlexWin;
use Inteleon\Dibs\Request\CurlRequest;
use Inteleon\Dibs\Request\RequestContract;
use Inteleon\Dibs\Exception\DibsFlexWinErrorException;
use PHPUnit_Framework_TestCase as TestSuite;

class DibsFlexWinTest extends TestSuite
{
    /**
     * @var Inteleon\Dibs\DibsFlexWin Mocked
     */
    protected $client;

    private $cfg;

    public function setUp()
    {
        parent::setUp();
        /**
         * Example of configuration
         *
         * @var array
         */
        $this->cfg = array(
            'merchant_id'       => getenv('DIBS_MERCHANT_ID'),
            'md5key_1'          => getenv('DIBS_MD5_KEY_1'),
            'md5key_2'          => getenv('DIBS_MD5_KEY_2'),
            'login_user'        => getenv('DIBS_LOGIN_USER'),
            'login_passwd'      => getenv('DIBS_LOGIN_PASS'),
            'accept_return_url' => getenv('DIBS_ACCEPT_RETURN_URL'),
            'cancel_return_url' => getenv('DIBS_CANCEL_RETURN_URL'),
            'callback_url'      => getenv('DIBS_CALLBACK_URL'),
            'currency_code'     => 'SEK',
            'language'          => 'sv',
            'test'              => true,
            'curlopts' => array(
                CURLOPT_SSL_VERIFYPEER    => false,
                CURLOPT_SSL_VERIFYHOST    => 2,
                CURLOPT_TIMEOUT_MS        => 30000,
                CURLOPT_CONNECTTIMEOUT_MS => 30000,
            ),
        );

    }

    /**
     * @test
     */
    public function getRequestClient()
    {
        $client = new DibsFlexWin($this->cfg);
        $this->assertInstanceOf(
            'Inteleon\Dibs\Request\RequestContract',
            $client->getRequest()
        );
    }

    /**
     * @test
     *
     */
    public function getStatusCodes()
    {
        $codes = DibsFlexWin::statusCodes();
        $this->assertEquals(true, is_array($codes));
    }

    /**
     * @test
     */
    public function captureTransaction()
    {
        $request = Mockery::mock('Inteleon\Dibs\Request\CurlRequest');
        $client = new DibsFlexWin($this->cfg, $request);
        $amount = 1000;
        $orderid = 1;
        $transact = 1;
        $request->shouldReceive('to')
        ->once()
        ->withArgs(array(
            'https://payment.architrade.com/cgi-bin/capture.cgi',
        ))->andReturn($request);

        $request->shouldReceive('post')
        ->once()
        ->andReturn($request);

        $request->shouldReceive('get')
        ->once()
        ->andReturn(array(
            'status'    => 'ACCEPTED',
            'transact'  => 'randomCode',
            'result'    => 0,
            'cardtype'  => 'VISA',
        ));

        $result = $client->captureTransaction($amount, $orderid, $transact);
        $this->assertArrayHasKey('status', $result);
    }

    /**
     * @test
     * Credit card expired
     *
     * @throws Inteleon\Dibs\Exception\DibsFlexWinErrorException
     */
    public function captureTransactionFailed()
    {
        $request = Mockery::mock('Inteleon\Dibs\Request\CurlRequest');
        $client = new DibsFlexWin($this->cfg, $request);
        $amount = 1000;
        $orderid = 1;
        $transact = 1;
        $request->shouldReceive('to')
        ->once()
        ->withArgs(array(
            'https://payment.architrade.com/cgi-bin/capture.cgi',
        ))->andReturn($request);

        $request->shouldReceive('post')
        ->once()
        ->andReturn($request);

        $request->shouldReceive('get')
        ->once()
        ->andReturn(array(
            'status'    => 'DECLINED',
            'transact'  => 'randomCode',
            'result'    => 3,
            'cardtype'  => 'VISA',
            'reason'    => 3, //Credit card expired
        ));
        try {
            $result = $client->captureTransaction($amount, $orderid, $transact);
            $this->fail('Expected DibsFlexWinErrorException expcetion');
        } catch (DibsFlexWinErrorException $e) {
            $this->assertEquals('Credit card expired.', $e->getMessage());
            $this->assertEquals(3, $e->getCode());
        }
    }

    /**
     * @test
     */
    public function authorizeTicket()
    {
        $request = Mockery::mock('Inteleon\Dibs\Request\CurlRequest');
        $client = new DibsFlexWin($this->cfg, $request);
        $amount = 1000;
        $orderid = 1;
        $transact = 1;
        $request->shouldReceive('to')
        ->once()
        ->withArgs(array(
            'https://payment.architrade.com/cgi-ssl/ticket_auth.cgi',
        ))->andReturn($request);

        $request->shouldReceive('post')
        ->once()
        ->andReturn($request);

        $request->shouldReceive('get')
        ->once()
        ->andReturn(array(
            'approvalcode'  => '',
            'authkey'       => 'authkey',
            'cardtype'      => '',
            'fee'           => '',
            'status'        => 'ACCEPTED',
            'transact'      => 123456
        ));

        $result = $client->authorizeTicket($amount, $orderid, $transact);
        $this->assertArrayHasKey('status', $result);
    }

    /**
     * @test
     * @throws Inteleon\Dibs\Exception\DibsFlexWinErrorException
     */
    public function authorizeTicketFailed()
    {
        $request = Mockery::mock('Inteleon\Dibs\Request\CurlRequest');
        $client = new DibsFlexWin($this->cfg, $request);
        $amount = 1000;
        $orderid = 1;
        $transact = 1;
        $request->shouldReceive('to')
        ->once()
        ->withArgs(array(
            'https://payment.architrade.com/cgi-ssl/ticket_auth.cgi',
        ))->andReturn($request);

        $request->shouldReceive('post')
        ->once()
        ->andReturn($request);

        $request->shouldReceive('get')
        ->once()
        ->andReturn(array(
            'reason'  => 'Returns a reason for the rejection.',
            'status'  => 'DECLINED',
        ));

        try {
            $result = $client->authorizeTicket(
                $amount,
                $orderid,
                $transact
            );
            $this->fail('Expected DibsFlexWinErrorException expcetion');
        } catch (\Exception $e) {
            $this->assertInstanceOf('Inteleon\Dibs\Exception\DibsFlexWinErrorException', $e);
        }
    }

    /**
     * @test
     */
    public function refundTransaction()
    {
        $request = Mockery::mock('Inteleon\Dibs\Request\CurlRequest');
        $client = new DibsFlexWin($this->cfg, $request);
        $amount = 1000;
        $orderid = 1;
        $transact = 1;
        $url = sprintf(
            'https://%s:%s@payment.architrade.com/cgi-adm/refund.cgi',
            $this->cfg['login_user'],
            $this->cfg['login_passwd']
        );

        $request->shouldReceive('to')
        ->once()
        ->withArgs(array(
            $url,
        ))->andReturn($request);

        $request->shouldReceive('post')
        ->once()
        ->andReturn($request);

        $request->shouldReceive('get')
        ->once()
        ->andReturn(array(
            'result'    => 0 //Accepted
        ));

        $result = $client->refundTransaction($amount, $orderid, $transact);
        $this->assertArrayHasKey('result', $result);
    }

    /**
     * @test
     */
    public function deleteCard()
    {
        $request = Mockery::mock('Inteleon\Dibs\Request\CurlRequest');
        $client = new DibsFlexWin($this->cfg, $request);
        $ticket = 1000;
        $url = sprintf(
            'https://%s:%s@payment.architrade.com/cgi-adm/delticket.cgi',
            $this->cfg['login_user'],
            $this->cfg['login_passwd']
        );

        $request->shouldReceive('to')
        ->once()
        ->withArgs(array(
            $url,
        ))->andReturn($request);

        $request->shouldReceive('post')
        ->once()
        ->andReturn($request);

        $request->shouldReceive('get')
        ->once()
        ->andReturn(array(
            'message' => 'If declined a reason is returned in this parameter.',
            'status' =>  'ACCEPTED'
        ));

        $result = $client->deleteCard($ticket);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * @test
     *
     * expectedException Inteleon\Dibs\Exception\DibsFlexWinErrorException
     */
    public function deleteCardFailed()
    {
        $request = Mockery::mock('Inteleon\Dibs\Request\CurlRequest');
        $client = new DibsFlexWin($this->cfg, $request);
        $ticket = 1000;
        $url = sprintf(
            'https://%s:%s@payment.architrade.com/cgi-adm/delticket.cgi',
            $this->cfg['login_user'],
            $this->cfg['login_passwd']
        );

        $request->shouldReceive('to')
        ->once()
        ->withArgs(array(
            $url,
        ))->andReturn($request);

        $request->shouldReceive('post')
        ->once()
        ->andReturn($request);

        $request->shouldReceive('get')
        ->once()
        ->andReturn(array(
            'message' => 'If declined a reason is returned in this parameter.',
            'status' =>  'ACCEPTED'
        ));
        $result = $client->deleteCard($ticket);
    }
}