<?php
namespace Inteleon\Dibs\Exception;

use Exception;

/**
 * Errors returned from Dibs webservice
 */
class DibsErrorException extends Exception
{
    /** @var array Dibs errors on Payment Authorization (auth.cgi, reauth.cgi, ticket_auth.cgi) */
    public static $errors_payment_authorization = array(
        "0" => "Rejected by acquirer.",
        "1" => "Communication problems.",
        "2" => "Error in the parameters sent to the DIBS server. An additional parameter called message is returned, with a value that may help identifying the error.",
        "3" => "Error at the acquirer.",
        "4" => "Credit card expired.",
        "5" => "Your shop does not support this credit card type, the credit card type could not be identified, or the credit card number was not modulus correct.",
        "6" => "Instant capture failed.",
        "7" => "The order number (orderid) is not unique.",
        "8" => "There number of amount parameters does not correspond to the number given in the split parameter.",
        "9" => "Control numbers (cvc) are missing.",
        "10" => "The credit card does not comply with the credit card type.",
        "11" => "Declined by DIBS Defender.",
        "20" => "Cancelled by user at 3D Secure authentication step.",
    );

    /** @var array Dibs errors on Payment handling (capture.cgi, refund.cgi, cancel.cgi, changestatus.cgi) */
    public static $errors_payment_handling = array(
        "0" => "Accepted.",
        "1" => "No response from acquirer.",
        "2" => "Timeout.",
        "3" => "Credit card expired.",
        "4" => "Rejected by acquirer.",
        "5" => "Authorisation older than 7 days.",
        "6" => "Transaction status on the DIBS server does not allow function.",
        "7" => "Amount too high.",
        "8" => "Error in the parameters sent to the DIBS server. An additional parameter called message is returned, with a value that may help identifying the error.",
        "9" => "Order number (orderid) does not correspond to the authorisation order number.",
        "10" => "Re-authorisation of the transaction was rejected.",
        "11" => "Not able to communicate with the acquier.",
        "12" => "Confirm request error.",
        "14" => "Capture is called for a transaction which is pending for batch - i.e. capture was already called.",
        "15" => "Capture was blocked by DIBS.",
        "1000" => "Refund accepted",
    );

    /**
     * Get description of error code
     *
     * @param int $code Error code
     * @return string Error description
     */
    public static function getPaymentAuthorizationErrorDesc($code)
    {
        return  isset(self::$errors_payment_authorization[$code])
                ? self::$errors_payment_authorization[$code]
                : $code;
    }

    /**
     * Get description of error code
     *
     * @param int $code Error code
     * @return string Error description
     */
    public static function getPaymentHandlingErrorDesc($code)
    {
        return  isset(self::$errors_payment_handling[$code])
                ? self::$errors_payment_handling[$code]
                : $code;
    }    
}