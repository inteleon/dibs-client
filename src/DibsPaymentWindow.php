<?php
namespace Inteleon\Dibs;

use Inteleon\Dibs\Exception\DibsException;
use Inteleon\Dibs\Exception\DibsConnectionException;
use Inteleon\Dibs\Exception\DibsErrorException;

class DibsPaymentWindow
{
	protected $config;

	public function __construct(array $config)
	{
		$config_default = array(
		    "accept_return_url" => "",
		    "callback_url" => "",
		    "cancel_return_url" => "",
		    "currency" => "",
		    "hmac_key" => "",
		    "language" => "",
		    "merchant_id" => "",
		    "test" => "1",
		    "curlopts" => array(),
		);
		$this->config = $config + $config_default;
	}

	/**
	 * Format a amount to Dibs format
	 * 
	 * @param decimal $amount Amount
	 * @return int $amount Amount
	 */
	public static function formatAmount($amount)
	{
		$amount = str_replace(",", ".", $amount);
		$amount = number_format($amount, 2, "", "");
		return $amount;
	}

	/**
	 * Calculates the MAC key from a set of parameters and a secret key
	 *
	 * @param array $params Params (key => value format)
	 * @return string
	 */
	public function calculateMac($params)
	{
		$hmac_key = $this->config['hmac_key'];

	    //Decode the hex encoded key
	    $hmac_key = pack('H*', $hmac_key);
	     
	    //Sort the key=>value array ASCII-betically according to the key
	    ksort($params, SORT_STRING);
     
	    //Create message from sorted array.
	    $params_urlstring = urldecode(http_build_query($params));

	    //Calculate and return the SHA-256 HMAC using algorithm for 1 key
	    return hash_hmac("sha256", $params_urlstring, $hmac_key);
	}

	/**
	 * Verify the MAC in a request/response
	 * 
	 * @param array $params Parameters
	 * @return void
	 */
	public function verifyMacResponse($params)
	{
		$mac = $params['MAC'];
		unset($params['MAC']);
		if ($mac != $this->calculateMac($params)) {
			throw new DibsException("Invalid MAC in response. WARNING: Check if the transaction was made in DIBS admin.");
		}
	}

	/**
	 * Sends a set of parameters to a DIBS API function
	 * @param string $payment_function The name of the target payment function, e.g. AuthorizeCard
	 * @param array $params A set of parameters to be posted in key => value format
	 * @return array API response
	 */
	protected function postToDIBS($payment_function, $params)
	{
		//Calculate MAC value for request
		$params["MAC"] = $this->calculateMac($params);		

		if ($this->config['test']) {
			$params['test'] = "1";
		}

		//Create JSON string from array of key => values
		$json_data = json_encode($params);

		//Set correct POST URL corresponding to the payment function requested
		switch ($payment_function) {
			case "AuthorizeCard":
				$post_url = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/AuthorizeCard";
				break;
			case "AuthorizeTicket":
				$post_url = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/AuthorizeTicket";
				break;
			case "CancelTransaction":
				$post_url = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/CancelTransaction";
				break;
			case "CaptureTransaction":
				$post_url = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/CaptureTransaction";
				break;
			case "CreateTicket":
				$post_url = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/CreateTicket";
				break;
			case "RefundTransaction":
				$post_url = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/RefundTransaction";
				break;
			case "Ping":
				$post_url = "https://api.dibspayment.com/merchant/v1/JSON/Transaction/Ping";
				break;
			default:
				throw new DibsException("Wrong input payment_function to postToDIBS");
		}

		$ch = curl_init();

		//Options not be overwritten
		$curlopts_definite = array(
			CURLOPT_URL => $post_url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => ("request=" . $json_data),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_NOSIGNAL => true, //http://www.php.net/manual/en/function.curl-setopt.php#104597
		);

		//Options customisable
		$curlopts_custom = array(
        	CURLOPT_SSL_VERIFYPEER => true,
        	CURLOPT_SSL_VERIFYHOST => 2,
        	CURLOPT_TIMEOUT_MS => 30000,
        	CURLOPT_CONNECTTIMEOUT_MS => 30000,
		);

		$curlopts_custom = $this->config['curlopts'] + $curlopts_custom; //Adding curlopts from config
		
		$curlopts = $curlopts_definite + $curlopts_custom;

		if (curl_setopt_array($ch, $curlopts) === false) {
			throw new DibsException('Failed setting curl options');
		}

		$result_json = curl_exec($ch);

		//Check for errors in the Curl operation
		if (curl_errno($ch) != 0) {
			throw new DibsConnectionException("Connection (curl) error " . curl_error($ch));
		}
		curl_close($ch);

		//Convert JSON server output to array of key => values
		$result_params = json_decode($result_json, true);

		return $result_params;
	}

	/**
	 * Makes a new authorization on an existing ticket using the AuthorizeTicket JSON service
	 *
	 * @param int $amount The amount of the purchase in smallest unit
	 * @param string $orderId The shops order ID for the purchase
	 * @param string $ticketId The ticket number on which the authorization should be done
	 * @return array API response
	 */
	public function authorizeTicket($amount, $orderId, $ticketId)
	{
		$params = array(
			"amount" => $amount,
			"currency" => $this->config['currency'],
			"merchantId" => $this->config['merchant_id'],
			"orderId" => $orderId,
			"ticketId" => $ticketId,
		);

		$result_params = $this->postToDIBS("AuthorizeTicket", $params);
		
		//Error code/string handling, thx dibs...
		//Dibs docs says that a string is returned but it seemts to be just a code
		if (!isset($result_params["declineReason"]) || $result_params["declineReason"] === "") {
			$decline_reason = "-";
		} elseif (preg_match('/[0-9]+/', $result_params["declineReason"])) {
			$decline_reason = DibsErrorException::getPaymentAuthorizationErrorDesc($result_params["declineReason"]);
		} else {
			$decline_reason = $result_params["declineReason"];
		}

		if ($result_params['status'] == "ACCEPT") {
			//Accept
		} elseif ($result_params["status"] == "DECLINE") {
			throw new DibsErrorException("AuthorizeTicket: DECLINE (" . $decline_reason . ")");
		} elseif ($result_params["status"] == "ERROR") {
			throw new DibsErrorException("AuthorizeTicket: ERROR (" . $decline_reason . ")");
		}

		return $result_params;
	}

	/**
	 * Captures a previously authorized transaction using the CaptureTransaction JSON service
	 *
	 * @param int $amount The amount of the capture in smallest unit
	 * @param string $transactionId The ticket number on which the authorization should be done
	 */
	public function captureTransaction($amount, $transactionId)
	{
		$params = array(
			"amount" => $amount,
			"merchantId" => $this->config['merchant_id'],
			"transactionId" => $transactionId,
		);

		$result_params = $this->postToDIBS("CaptureTransaction", $params);

		//Error code/string handling, thx dibs...
		//Dibs docs says that a string is returned but it seemts to be just a code
		if (!isset($result_params["declineReason"]) || $result_params["declineReason"] === "") {
			$decline_reason = "-";
		} elseif (preg_match('/[0-9]+/', $result_params["declineReason"])) {
			$decline_reason = DibsErrorException::getPaymentHandlingErrorDesc($result_params["declineReason"]);
		} else {
			$decline_reason = $result_params["declineReason"];
		}

		if ($result_params['status'] == "ACCEPT") {
			//Accept
		} elseif ($result_params["status"] == "PENDING") {
			//Accept but pending (PENDING means that the transaction has been successfully added for a batch capture. The result of the capture can be found in the administration.)	
		} elseif ($result_params["status"] == "DECLINE") {
			throw new DibsErrorException("CaptureTransaction: DECLINE (" . $decline_reason . ")");
		} elseif ($result_params["status"] == "ERROR") {
			throw new DibsErrorException("CaptureTransaction: ERROR (" . $decline_reason . ")");
		}

		return $result_params;
	}

	/**
	 * Refunds a previously captured transaction using the RefundTransaction JSON service
	 * 
	 * @param int $amount The amount of the capture in smallest unit
	 * @param string $transactionId The ticket number on which the authorization should be done
	 * @return array API response
	 */
	public function refundTransaction($amount, $transactionId)
	{
		$params = array(
			"amount" => $amount,
			"merchantId" => $this->config['merchant_id'],
			"transactionId" => $transactionId,
		);

		$result_params = $this->postToDIBS("RefundTransaction", $params);

		//Error code/string handling, thx dibs...
		//Dibs docs says that a string is returned but it seemts to be just a code
		if (!isset($result_params["declineReason"]) || $result_params["declineReason"] === "") {
			$decline_reason = "-";
		} elseif (preg_match('/[0-9]+/', $result_params["declineReason"])) {
			$decline_reason = DibsErrorException::getPaymentHandlingErrorDesc($result_params["declineReason"]);
		} else {
			$decline_reason = $result_params["declineReason"];
		}

		if ($result_params['status'] == "ACCEPT") {
			//Accept
		} elseif ($result_params["status"] == "PENDING") {
			//Accept but pending
		} elseif ($result_params["status"] == "DECLINE") {
			throw new DibsErrorException("RefundTransaction: DECLINE (" . $decline_reason . ")");
		} elseif ($result_params["status"] == "ERROR") {
			throw new DibsErrorException("RefundTransaction: ERROR (" . $decline_reason . ")");
		}
	
		return $result_params;
	}

	/**
	 * Cancels an existing transaction using the CancelTransaction JSON service
	 * 
	 * @param string $transactionId The transaction number which should be cancelled
	 * @return array API response
	 */
	public function cancelTransaction($transactionId)
	{
		$params = array(
			"merchantId" => $this->config['merchant_id'],
			"transactionId" => $transactionId,
		);

		$result_params = $this->postToDIBS("CancelTransaction", $params);

		//Error code/string handling, thx dibs...
		//Dibs docs says that a string is returned but it seemts to be just a code
		if (!isset($result_params["declineReason"]) || $result_params["declineReason"] === "") {
			$decline_reason = "-";
		} elseif (preg_match('/[0-9]+/', $result_params["declineReason"])) {
			$decline_reason = DibsErrorException::getPaymentHandlingErrorDesc($result_params["declineReason"]);
		} else {
			$decline_reason = $result_params["declineReason"];
		}
		
		if ($result_params['status'] == "ACCEPT") {
			//Accept
		} elseif ($result_params["status"] == "DECLINE") {
			throw new DibsErrorException("CancelTransaction: DECLINE (" . $decline_reason . ")");
		} elseif ($result_params["status"] == "ERROR") {
			throw new DibsErrorException("CancelTransaction: ERROR (" . $decline_reason . ")");
		}

		return $result_params;
	}

	/**
	 * Get the URL to the external Payment Window
	 * 
	 * @return string The URL
	 */
	public function getPaymentWindowUrl()
	{
		return "https://sat1.dibspayment.com/dibspaymentwindow/entrypoint";
	}

	/**
	 * Get parameters for the form posting to the external Payment Window
	 * 
	 * @param int $amount Amount
	 * @param int $orderId Order id
	 * @param boolean $createTicket Create ticket instead of normal transaction
	 * @param array $custom_params Custom params to send (key names should start with s_)
	 * @return array Parameters
	 * @todo Make sure only custom_params only have keys starting with s_ (see DIBS docs why)
	 */
	public function getPaymentWindowParams($amount, $orderId, $createTicket = false, $custom_params = array())
	{
		$params = array(
			"acceptReturnUrl" => $this->config['accept_return_url'],
			"amount" => $amount,
			"callbackUrl" => $this->config['callback_url'],
			"cancelReturnUrl" => $this->config['cancel_return_url'],
			"currency" => $this->config['currency'],
			"language" => $this->config['language'],
			"merchant" => $this->config['merchant_id'],
			"orderId" => $orderId,
		);

		if ($this->config['test']) {
			$params['test'] = "1";
		}

		if ($createTicket) {
			$params['createTicket'] = "1";
		}

		$params = array_merge($params, $custom_params);

		$params["MAC"] = $this->calculateMac($params);

		return $params;
	}

	/**
	 * Get Payment Window response data (POST)
	 *
	 * @return array Dibs parameters
	 * @see http://tech.dibspayment.com/DPW_hosted_output_parameters_return_parameters
	 */
	public function getPaymentWindowResultParams($params = null)
	{
		if ($params === null) {
			$params = $_POST;
		}

		$this->verifyMacResponse($params);

		//TODO: captureStatus? see manual

		$validation_errors = isset($params['validationErrors']) && $params['validationErrors'] ? (" (Validation errors: " . $params['validationErrors'] . ")") : "";

		//Ticket registration
		if (isset($params['ticket'])) {
			if ($params['ticketStatus'] == "ACCEPTED") {
				//Accepted
			} elseif ($params['ticketStatus'] == "DECLINED") {
				throw new DibsErrorException("DECLINED: Ticket creation was declined by DIBS or the acquirer." . $validation_errors);
			} elseif ($params['ticketStatus'] == "ERROR") {
				throw new DibsErrorException("ERROR: An error happened. More information is available in DIBS Admin." . $validation_errors);
			}
		} else {
			if ($params['status'] == "ACCEPTED") {
				//Accepted
			} elseif ($params['status'] == "PENDING") {
				//Accepted but PENDING		
			} elseif ($params['status'] == "DECLINED") {
				throw new DibsErrorException("DECLINED: Transaction declined." . $validation_errors);
			} elseif ($params['status'] == "CANCELLED") {
				throw new DibsErrorException("CANCELLED: Payment cancelled by user." . $validation_errors);
			}
		}

		return $params;
	}

	/**
	 * Charge card (Authorize + Capture)
	 * 
	 * @param int $amount Amount
	 * @param int $orderId Order id
	 * @param int $ticketId Ticket id
	 * @return array API response from capture
	 */
	public function chargeCard($amount, $orderId, $ticketId)
	{
		$result_params_auth = $this->authorizeTicket($amount, $orderId, $ticketId);
		$transactionId = $result_params_auth['transactionId'];
		$result_params_capture = $this->captureTransaction($amount, $transactionId);
		return ($result_params_capture + $result_params_auth);
	}
}