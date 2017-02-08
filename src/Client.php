<?php

namespace SimpleNeo4j;

class Client
{
    /**
     * @var string
     */
    private $_host;

    /**
     * @var string
     */
    private $_port;

    /**
     * @var string
     */
    private $_username;

    /**
     * @var string
     */
    private $_password;

    /**
     * @var bool
     */
    private $_secure;

    /**
     * @var \GuzzleHttp\Client
     */
    private $_http_client;

    /**
     * @var string
     */
    private $_protocol;

    /**
     * @var array
     */
    private $_query_queue = [];

    /**
     * @param string $host
     * @param string $username
     * @param string $password
     * @param bool $secure
     * @param int|null $port
     * @param \GuzzleHttp\Client|null $http_client
     */
    public function __construct(string $host, string $username = '', string $password = '', bool $secure = false, int $port = null, \GuzzleHttp\Client $http_client = null )
    {
        $this->_host = $host;
        $this->_port = $port;
        $this->_username = $username;
        $this->_password = $password;
        $this->_secure = $secure;

        if ($port !== null) {
            $this->_port = $port;
        } else {
            $this->_port = $secure ? 7473 : 7474;
        }

        $this->_http_client = $http_client ?: new \GuzzleHttp\Client();
        $this->_protocol = $secure ? 'https' : 'http';
    }

    public function getHost()
    {
        return $this->_host;
    }

    public function getPort()
    {
        return $this->_port;
    }

    public function isSecure()
    {
        return $this->_secure;
    }

    public function getProtocol()
    {
        return $this->_protocol;
    }

    public function executeQuery( string $query, array $params = [] )
    {

    }
}
