<?php

namespace Inteleon\Dibs;

use Inteleon\Dibs\Request\CurlRequest;
use Inteleon\Dibs\Request\RequestContract;
use Inteleon\Dibs\Exception\DibsErrorException;
use Inteleon\Dibs\Exception\DibsFlexWinAuthException;
use Inteleon\Dibs\Exception\DibsFlexWinPaymentException;

/**
 * DIBS FlexWin Payment handling
 */
class DibsFlexWin
{
    /**
     * Config variables:
     * merchant_id integer
     * md5key_1 string
     * md5key_2 string
     * login_user string
     * login_passwd string
     * accept_return_url string
     * cancel_return_url string
     * callback_url string
     * currency_code string
     * language string
     * test boolean
     * curlopts array
     *
     * @var array
     */
    protected $config;

    /**
     * Request object
     *
     * @var \Inteleon\Dibs\Request\RequestContract
     */
    protected $request;

    /**
     *
     * @param array                                  $config
     * @param \Inteleon\Dibs\Request\RequestContract $request
     * optional default method is with curl
     */
    public function __construct(array $config, $request = null)
    {
        $this->setConfig($config);
        if ($request instanceof RequestContract) {
            $this->request = $request;
        } else {
            $this->request = new CurlRequest($this->config['curlopts']);
        }
    }

    /**
     *
     * @return Inteleon\Dibs\Request\RequestContract
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * set config variables
     *
     * @param array $config array
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * Charge card
     *
     * @param  string $amount
     * @param  string $orderid
     * @param  string $ticket
     * @throws DibsFlexWinPaymentException
     *
     * @return array Result from DIBS capture.cgi
     */
    public function chargeCard($amount, $orderid, $ticket)
    {
        $ticket_return = $this->authorizeTicket($amount, $orderid, $ticket);
        return $this->captureTransaction($amount, $orderid, $ticket_return["transact"]);
    }

    /**
     * Refund card
     *
     * @param  string $amount
     * @param  string $orderid
     * @param  string $transact
     * @return array Result from DIBS refund.cgi
     */
    public function refundCard($amount, $orderid, $transact)
    {
        $result_params = $this->refundTransaction($amount, $orderid, $transact);

        if ($result_params["result"] != "1000" && $result_params["result"] != "0") {
            $message = isset($result_params["message"]) ? $result_params["message"] : "DECLINED";
            throw new DibsFlexWinAuthException($message, $result_params["result"]);
        }

        return $result_params;
    }

    /**
     * Flexwin base url
     *
     * @return string
     */
    public function getFlexWinUrl()
    {
        return "https://payment.architrade.com/paymentweb/start.action";
    }

    /**
     * Build parameters for getFlexWinUrl() POST
     *
     * @param integer $amount
     * @param integer $orderid
     * @param bool    $preauth
     * @param array   $custom_params
     *
     * @return array
     */
    public function getFlexWinParams($amount, $orderid, $preauth = false, $custom_params = array())
    {
        $parameters = array_merge(
            array(
            "accepturl"     => $this->config['accept_return_url'],
            "amount"        => $amount,
            "callbackurl"   => $this->config['callback_url'],
            "cancelurl"     => $this->config['cancel_return_url'],
            "currency"      => $this->config['currency_code'],
            "lang"          => $this->config['language'],
            "orderid"       => $orderid,
            ),
            $custom_params
        );

        $params = $this->defaultParameters($parameters);

        if ($preauth == true) {
            $params['preauth'] = "true";
            $params['zero_auth'] = "true";
        }

        if (!array_key_exists('maketicket', $params)) {
            $params["md5key"] = $this->calculateMD5(
                array(
                "merchant" => $params['merchant'],
                "orderid"  => $params['orderid'],
                "currency" => $params['currency'],
                "amount"   => $params['amount']
                )
            );
        }

        return $params;
    }


    /**
     * @param  array $params
     *
     * @return array
     */
    public function getFlexWinResultParams($params = null)
    {
        if ($params === null) {
            $params = $_POST;
        }

        if ($params['preauth'] == "true") {
            //Verify the MD5 for the response
            $hash = $this->calculateMD5(
                array(
                    "transact" => $params['transact'],
                    "preauth"  => "true",
                    "currency" => $params['currency']
                    )
            );
            if ($params['authkey'] != $hash) {
                throw new DibsFlexWinPaymentException("Invalid MD5 for response");
            }
        } else {
            //Verify the MD5 for the response
            $response = $this->calculateMD5(
                array(
                    "transact" => $params['transact'],
                    "amount"   => $params['amount'],
                    "currency" => $params['currency']
                    )
            );
            if ($params['authkey'] != $response) {
                throw new DibsFlexWinPaymentException("Invalid MD5 for response");
            }
        }

        return $params;
    }


    /**
     * Calculate md5 hash
     *
     * @link http://tech.dibspayment.com/D2/FlexWin/API/MD5
     *
     * @param array $params
     *
     * @return string
     */
    public function calculateMD5($params)
    {
        return md5($this->config['md5key_2'] . md5($this->config['md5key_1'] . urldecode(http_build_query($params))));
    }

    /**
     * @link http://tech.dibspayment.com/D2/FlexWin/API/MD5
     *
     * @param  array  $params
     * @param  string $function_name
     *
     * @return string
     */
    protected function calculateMD5ForApiRequest($params, $function_name)
    {
        switch ($function_name) {
            case "3dsecure.cgi":
            case "auth.cgi":
                $md5_params = array(
                "merchant" => $params['merchant'],
                "orderid"  => $params['orderid'],
                "currency" => $params['currency'],
                "amount"   => $params['amount'],
                );
                break;
            case "cancel.cgi":
                $md5_params = array(
                "merchant" => $params['merchant'],
                "orderid"  => $params['orderid'],
                "transact" => $params['transact'],
                "amount"   => $params['amount'],
                );
                break;
            case "capture.cgi":
            case "refund.cgi":
            case "suppl_auth.cgi":
                $md5_params = array(
                "merchant" => $params['merchant'],
                "orderid"  => $params['orderid'],
                "transact" => $params['transact'],
                "amount"   => $params['amount'],
                );
                break;
            case "ticket_auth.cgi":
                $md5_params = array(
                "merchant" => $params['merchant'],
                "orderid"  => $params['orderid'],
                "ticket"   => $params['ticket'],
                "currency" => $params['currency'],
                "amount"   => $params['amount'],
                );
                break;
            case "delticket.cgi":
                return false;
            default:
                throw new DibsFlexWinPaymentException("Invalid API function name for MD5 calculation (request)");
        }

        return $this->calculateMD5($md5_params);
    }


    /**
     *
     * @param  array  $params
     * @param  array  $return_params
     * @param  string $function_name
     *
     * @return string
     */
    protected function calculateMD5ForApiResponse($params, $return_params, $function_name)
    {
        switch ($function_name) {
            case "ticket_auth.cgi":
                $md5_params = array(
                "transact" => $return_params['transact'],
                "amount" => $params['amount'],
                "currency" => $params['currency'],
                );
                break;
            default:
                throw new DibsFlexWinPaymentException("Invalid API function name for MD5 calculation (response)");
        }

        return $this->calculateMD5($md5_params);
    }

    /**
     * Send POST request to dibs based on $payment_function
     *
     * @param  string $payment_function
     * @param  string $params
     * @return string
     * @throws DibsFlexWinPaymentException
     */
    protected function postToDibs($payment_function, $params)
    {
        //Calculate MD5 value for request (not for all requests)
        if ($md5key = $this->calculateMD5ForApiRequest($params, $payment_function)) {
            $params["md5key"] = $md5key;
        }

        switch ($payment_function) {
            case "ticket_auth.cgi":
                $post_url = "https://payment.architrade.com/cgi-ssl/ticket_auth.cgi";
                break;
            case "delticket.cgi":
                $post_url = "https://" . $this->config['login_user'] . ":" . $this->config['login_passwd'] . "@payment.architrade.com/cgi-adm/delticket.cgi";
                break;
            case "capture.cgi":
                $post_url = "https://payment.architrade.com/cgi-bin/capture.cgi";
                break;
            case "refund.cgi":
                $post_url = "https://" . $this->config['login_user'] . ":" . $this->config['login_passwd'] . "@payment.architrade.com/cgi-adm/refund.cgi";
                break;
            default:
                throw new DibsFlexWinPaymentException("Unkown DIBS function");
        }

        return $this->getRequest()->to($post_url)->post($params)->get();
    }

    /**
     * Check card and reserve $amount from card
     *
     * @link http://tech.dibspayment.com/D2_ticketauthcgi
     *
     * @param integer $amount
     * @param integer $orderid
     * @param integer $ticket
     *
     * @throws Inteleon\Dibs\Exception\DibsFlexWinPaymentException
     *
     * @return array
     */
    public function authorizeTicket($amount, $orderid, $ticket)
    {
        $params = $this->defaultParameters(
            array(
            "amount"    => $amount,
            "currency"  => $this->config['currency_code'],
            "orderid"   => $orderid,
            "ticket"    => $ticket,
            "textreply" => 1,
            )
        );

        $result = $this->postToDibs("ticket_auth.cgi", $params);
        if ($result['status'] == 'ACCEPTED') {
            return $result;
        }
        if (isset($result['message'])) {
            throw new DibsFlexWinAuthException($result['message'], $result['reason']);
        }
        throw new DibsFlexWinAuthException($result['reason']);
    }

    /**
     * Capture
     *
     * @link http://tech.dibspayment.com/D2_capturecgi
     *
     * @param integer $amount   (ISO4217)
     * @param integer $orderid
     * @param integer $transact DIBS identification number
     *                          the transact is a as minimum 6-digit integer
     *
     * @throws Inteleon\Dibs\Exception\DibsFlexWinPaymentException
     *
     * @return mixed
     */
    public function captureTransaction($amount, $orderid, $transact)
    {
        $params = $this->defaultParameters(
            array(
            "amount"    => $amount,
            "orderid"   => $orderid,
            "transact"  => $transact,
            "textreply" => 1,
            )
        );
        $result = $this->postToDibs("capture.cgi", $params);

        if ($result['status'] == 'ACCEPTED') {
            return $result;
        }
        if (isset($result['message'])) {
            throw new DibsFlexWinPaymentException($result['message'], $result['reason']);
        }
        throw new DibsFlexWinPaymentException($result['reason']);
    }

    /**
     * Refund
     *
     * @link http://tech.dibspayment.com/D2_refundcgi
     *
     * @param integer $amount
     * @param integer $orderid
     * @param integer $transact
     *
     * @return array
     */
    public function refundTransaction($amount, $orderid, $transact)
    {
        $params = $this->defaultParameters(
            array(
            "amount"    => $amount,
            "currency"  => $this->config['currency_code'],
            "orderid"   => $orderid,
            "transact"  => $transact,
            "textreply" => 1,
            )
        );

        return $this->postToDibs("refund.cgi", $params);
    }

    /**
     * alias for deleteTicket()
     *
     * @param string $ticket
     *
     * @throws DibsFlexWinPaymentException
     *
     * @return array
     */
    public function deleteCard($ticket)
    {
        return $this->deleteTicket($ticket);
    }

    /**
     * Delete ticket
     *
     * @link http://tech.dibspayment.com/D2_delticketcgi
     *
     * @param integer $ticket
     *
     * @throws DibsFlexWinPaymentException
     *
     * @return array
     */
    public function deleteTicket($ticket)
    {
        $params = $this->defaultParameters(
            array(
            "ticket" => $ticket,
            )
        );

        $result = $this->postToDibs("delticket.cgi", $params);

        if ($result["status"] == "ACCEPTED") {
            return $result;
        }
        throw new DibsFlexWinPaymentException($result["message"]);
    }

    /**
     * Check if it is test enviorment
     *
     * @return boolean
     */
    public function isTest()
    {
        if (isset($this->config['test']) && $this->config['test'] == true) {
            return true;
        }
        return false;
    }

    /**
     * @param  array $optional
     *
     * @return array
     */
    protected function defaultParameters(array $optional)
    {
        $default = array(
            "merchant"  => $this->config['merchant_id'],
        );
        if ($this->isTest()) {
            $default['test'] = "1";
        }
        if (!empty($optional)) {
            return array_merge($default, $optional);
        }
        return $default;
    }

    /**
     * Get config attribute
     *
     * @param mixed $variable
     *
     * @return mixed
     */
    public function __get($variable)
    {
        if (isset($this->config[$variable])) {
            return $this->config[$variable];
        }
        return null;
    }

    /**
     * Dibs status code
     *
     * @link http://tech.dibspayment.com/nodeaddpage/toolboxstatuscodes
     *
     * @return array
     */
    public static function statusCodes()
    {
        return array(
            0   => 'transaction inserted',
            1   => 'declined',
            2   => 'authorization approved',
            3   => 'capture sent to acquirer',
            4   => 'capture declined by acquirer',
            5   => 'capture completed',
            6   => 'authorization deleted',
            7   => 'capture balanced',
            8   => 'partially refunded and balanced',
            9   => 'refund sent to acquirer',
            10  => 'refund declined',
            11  => 'refund completed',
            12  => 'capture pending',
            13  => '"ticket" transaction',
            14  => 'deleted "ticket" transaction',
            15  => 'refund pending',
            16  => 'waiting for shop approval',
            17  => 'declined by DIBS',
            18  => 'multicap transaction open',
            19  => 'multicap transaction closed',
        );
    }
}
