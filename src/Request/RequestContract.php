<?php

namespace Inteleon\Dibs\Request;

interface RequestContract
{
    /**
     * URL to send request to
     *
     * @return $this
     */
    public function to($url);

    /**
     * Send a HTTP POST request
     *
     * @param  array  $body
     * @throws \Inteleon\Dibs\Exception\DibsConnectionException
     * @return mixed
     */
    public function post(array $body);
}
