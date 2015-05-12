<?php
namespace Inteleon\Dibs;
use DibsFlexWinAuthorizationException;
use DibsFlexWinPaymentException;
use Inteleon\Dibs\Exception\DibsFlexWinException;

/**
 * DIBS FlexWin Payment handling
 */
class DibsFlexWin
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }


    /**
     * Charge card
     *
     * @param string $amount
     * @param string $orderid
     * @param string $ticket
     * @return array Result from DIBS capture.cgi
     */
    public function chargeCard($amount, $orderid, $ticket)
    {
        $result_params = $this->authorizeTicket($amount, $orderid, $ticket);

        if ($result_params["status"] == "DECLINED") {
            $message = isset($result_params["message"]) ? $result_params["message"] : "DECLINED";
            throw new DibsFlexWinAuthorizationException($message, $result_params["reason"]);
        }

        $result_params = $this->captureTransaction($amount, $orderid, $result_params["transact"]);

        if ($result_params["status"] == "DECLINED") {
            $message = isset($result_params["message"]) ? $result_params["message"] : "DECLINED";
            throw new DibsFlexWinPaymentException($message, $result_params["reason"]);
        }

        return $result_params;
    }


    /**
     * Refund card
     *
     * @param string $amount
     * @param string $orderid
     * @param string $transact
     * @return array Result from DIBS refund.cgi
     */
    public function refundCard($amount, $orderid, $transact)
    {
        $result_params = $this->refundTransaction($amount, $orderid, $transact);

        if ($result_params["result"] != "1000" && $result_params["result"] != "0") {
            $message = isset($result_params["message"]) ? $result_params["message"] : "DECLINED";
            throw new DibsFlexWinPaymentException($message, $result_params["result"]);
        }

        return $result_params;
    }


    public function deleteCard($ticket)
    {
        $result_params = $this->deleteTicket($ticket);

        if ($result_params["status"] == "DECLINED") {
            throw new DibsFlexWinException($result_params["message"]);
        }

        return $result_params;
    }


    public function getFlexWinUrl()
    {
        return "https://payment.architrade.com/paymentweb/start.action";
    }


    public function getFlexWinParams($amount, $orderid, $preauth = false, $custom_params = array())
    {
        $params = array(
            "accepturl" 	=> $this->config['accept_return_url'],
            "amount"    	=> $amount,
            "callbackurl" 	=> $this->config['callback_url'],
            "cancelurl" 	=> $this->config['cancel_return_url'],
            "currency"  	=> $this->config['currency_code'],
            "lang"  		=> $this->config['language'],
            "merchant"  	=> $this->config['merchant_id'],
            "orderid"   	=> $orderid,
        );

        if ($preauth) {
            $params['preauth'] = "true";
        }

        if ($this->config['test']) {
            $params['test'] = "1";
        }

        $params = array_merge($params, $custom_params);

        if (!array_key_exists('maketicket', $params)) {
            $params["md5key"] = $this->calculateMD5(array("merchant" => $params['merchant'], "orderid" => $params['orderid'], "currency" => $params['currency'], "amount" => $params['amount']));
        }

        return $params;
    }


    public function getFlexWinResultParams($params = null)
    {
        if ($params === null) {
            $params = $_POST;
        }

        if ($params['preauth'] == "true") {

            //Verify the MD5 for the response
            if ($params['authkey'] != $this->calculateMD5(array("transact" => $params['transact'], "preauth" => "true", "currency" => $params['currency']))) {
                throw new DibsFlexWinException("Invalid MD5 for response");
            }

        } else {

            //Verify the MD5 for the response
            if ($params['authkey'] != $this->calculateMD5(array("transact" => $params['transact'], "amount" => $params['amount'], "currency" => $params['currency']))) {
                throw new DibsFlexWinException("Invalid MD5 for response");
            }

        }

        return $params;
    }


    public function calculateMD5($params)
    {
        return md5($this->config['md5key_2'] . md5($this->config['md5key_1'] . urldecode(http_build_query($params))));
    }


    protected function calculateMD5ForApiRequest($params, $function_name)
    {
        switch ($function_name) {
            case "3dsecure.cgi":
            case "auth.cgi":
                $md5_params = array(
                    "merchant" => $params['merchant'],
                    "orderid" => $params['orderid'],
                    "currency" => $params['currency'],
                    "amount" => $params['amount'],
                );
                break;
            case "cancel.cgi":
                $md5_params = array(
                    "merchant" => $params['merchant'],
                    "orderid" => $params['orderid'],
                    "transact" => $params['transact'],
                    "amount" => $params['amount'],
                );
                break;
            case "capture.cgi":
            case "refund.cgi":
            case "suppl_auth.cgi":
                $md5_params = array(
                    "merchant" => $params['merchant'],
                    "orderid" => $params['orderid'],
                    "transact" => $params['transact'],
                    "amount" => $params['amount'],
                );
                break;
            case "ticket_auth.cgi":
                $md5_params = array(
                    "merchant" => $params['merchant'],
                    "orderid" => $params['orderid'],
                    "ticket" => $params['ticket'],
                    "currency" => $params['currency'],
                    "amount" => $params['amount'],
                );
                break;
            case "delticket.cgi":
                return false;
            default:
                throw new DibsFlexWinException("Invalid API function name for MD5 calculation (request)");
        }

        return $this->calculateMD5($md5_params);
    }


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
                throw new DibsFlexWinException("Invalid API function name for MD5 calculation (response)");
        }

        return $this->calculateMD5($md5_params);
    }


    /**
     * Skickar formulärdatan till DIBS för behandling.
     *
     * @param string $payment_function
     * @param string $params
     * @return string
     * @throws DibsFlexWinException
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
                throw new DibsFlexWinException("Unkown DIBS function");
        }

        $ch = curl_init();

        //Default options not be overwritten
        $curlopts = array(
            CURLOPT_URL => $post_url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => true, //http://www.php.net/manual/en/function.curl-setopt.php#104597
        );

        $curlopts = $curlopts + $this->config['curlopts']; //Adding custom curl options

        if (curl_setopt_array($ch, $curlopts) === false) {
            throw new DibsFlexWinException('Failed setting curl options');
        }

        $result = curl_exec($ch);

        //Check for errors in the Curl operation
        if (curl_errno($ch) != 0) {
            throw new DibsFlexWinException("Curl error " . curl_error($ch));
        }
        curl_close($ch);

        $return_params = array();
        parse_str($result, $return_params);

        /*
        if (isset($return_params['authkey']) && $return_params['authkey'] != $this->calculateMD5ForApiResponse($params, $return_params, $payment_function)) {
            throw new DibsFlexWinException("Invalid MD5 for response");
        }
        */

        return $return_params;
    }


    /**
     * Kontrollerar kort och reserverar belopp som ska dras från kunds kort.
     *
     */
    public function authorizeTicket($amount, $orderid, $ticket)
    {
        $params = array(
            "amount" => $amount,
            "currency" => $this->config['currency_code'],
            "merchant" => $this->config['merchant_id'],
            "orderid" => $orderid,
            "ticket" => $ticket,
            "textreply" => 1,
        );

        if ($this->config['test']) {
            $params['test'] = "1";
        }

        return $this->postToDibs("ticket_auth.cgi", $params);
    }


    /**
     * Drar belopp från kundens kort.
     *
     */
    public function captureTransaction($amount, $orderid, $transact)
    {
        $params = array(
            "amount" => $amount,
            "merchant" => $this->config['merchant_id'],
            "orderid" => $orderid,
            "transact" => $transact,
            "textreply" => 1,
        );

        if ($this->config['test']) {
            $params['test'] = "1";
        }

        return $this->postToDibs("capture.cgi", $params);
    }


    /**
     * Krediterar ett kunds kort, dvs gör en återbetalning.
     *
     */
    public function refundTransaction($amount, $orderid, $transact)
    {
        $params = array(
            "amount" => $amount,
            "currency" => $this->config['currency_code'],
            "merchant" => $this->config['merchant_id'],
            "orderid" => $orderid,
            "transact" => $transact,
            "textreply" => 1,
        );

        if ($this->config['test']) {
            $params['test'] = "1";
        }

        return $this->postToDibs("refund.cgi", $params);
    }


    public function deleteTicket($ticket)
    {
        $params = array(
            "merchant" => $this->config['merchant_id'],
            "ticket" => $ticket,
        );

        if ($this->config['test']) {
            $params['test'] = "1";
        }

        return $this->postToDibs("delticket.cgi", $params);
    }
}