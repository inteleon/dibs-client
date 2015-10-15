<?php

namespace Inteleon\Dibs\Request;

use Inteleon\Dibs\Exception\DibsConnectionException;

class CurlRequest implements RequestContract
{
    /**
     * $url to send request to
     *
     * @var string
     */
    public $url;

    /**
     * cURL configuration variables
     *
     * @var array
     */
    private $curl_config;

    /**
     * ch instance response
     *
     * @var ch (mixed)
     */
    public $response;

    public function __construct(array $config = array())
    {
        if (!extension_loaded('curl')) {
            throw new DibsException(
                "Extension cURL required for this implementation. ".
                "You can create your own Request instance if it " .
                "adheres to Inteleon\Dibs\Request\RequestContract interface"
            );
        }
        $this->to_array = false;
        $this->curl_config = $config;
    }

    /**
     * URL to send request to
     *
     * @return $this
     */
    public function to($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Send a HTTP POST request
     *
     * @param  array  $body
     *
     * @return array
     */
    public function post(array $body)
    {
        if ($this->url == null) {
            throw new DibsConnectionException("No valid url: {$this->url}");
        }
        $request = array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            //http://www.php.net/manual/en/function.curl-setopt.php#104597
            // CURLOPT_NOSIGNAL       => true,
            CURLOPT_URL            => $this->url,
            CURLOPT_POSTFIELDS     => $body,
        );

        $curlopts = $this->curl_config + $request;

        $ch = curl_init();

        if (curl_setopt_array($ch, $curlopts) === false) {
            throw new DibsConnectionException('Failed setting curl options');
        }

        $this->response = curl_exec($ch);
        //Check for errors in the Curl operation
        if (curl_errno($ch) != 0) {
            throw new DibsConnectionException("Connection (curl) error " . curl_error($ch));
        }
        curl_close($ch);

        return $this;
    }

    /**
     * get default response
     *
     * @return array
     */
    public function get()
    {
        $return_params = array();
        parse_str($this->response, $return_params);
        return $return_params;
    }
}
