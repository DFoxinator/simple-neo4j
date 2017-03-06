<?php

namespace SimpleNeo4j;

class Client
{
    const CONFIG_HOST = 'host';
    const CONFIG_PORT = 'port';
    const CONFIG_SECURE = 'secure';
    const CONFIG_PROTOCOL = 'protocol';
    const CONFIG_ERROR_MODE = 'error_mode';
    const CONFIG_NO_SSL_VERIFY = 'no_ssl_verify';

    const ERROR_MODE_HIDE_ERRORS = 'hide';
    const ERROR_MODE_THROW_ERRORS = 'throw';

    const NEO4J_CYPHER_ENDPOINT = 'db/data/transaction/commit';

    const DEFAULT_CONFIG = [
        self::CONFIG_HOST => 'localhost',
        self::CONFIG_PORT => 7474,
        self::CONFIG_SECURE => false,
        self::CONFIG_PROTOCOL => 'http',
        self::CONFIG_ERROR_MODE => '',
        self::CONFIG_NO_SSL_VERIFY => false,
    ];

    /**
     * @var \GuzzleHttp\Client
     */
    private $_http_client;

    /**
     * @var array
     */
    private $_config;

    /**
     * @var array
     */
    private $_query_batch = [];

    /**
     * @param array $config
     * @param \GuzzleHttp\Client|null $http_client
     */
    public function __construct(array $config = [], \GuzzleHttp\Client $http_client = null)
    {
        $this->_setConfigFromOptions($config);
        $this->_http_client = $http_client ?: new \GuzzleHttp\Client();
    }

    /**
     * @return string
     */
    public function getHost() : string
    {
        return $this->_config[self::CONFIG_HOST];
    }

    /**
     * @return int
     */
    public function getPort() : int
    {
        return $this->_config[self::CONFIG_PORT];
    }

    /**
     * @return bool
     */
    public function isSecure() : bool
    {
        return $this->_config[self::CONFIG_SECURE];
    }

    /**
     * @return string
     */
    public function getProtocol() : string
    {
        return $this->_config[self::CONFIG_PROTOCOL];
    }

    public function addQueryToBatch(string $query, array $params = [])
    {
        $this->_query_batch[] = [
            'statement' => $query,
            'parameters' => (object)$params,
        ];
    }

    /**
     * @return \SimpleNeo4j\ResultSet
     *
     * @throws \SimpleNeo4j\Exception\CypherQueryException
     */
    public function executeBatchQueries()
    {
        if (!$this->_query_batch) {
            return new ResultSet();
        }

        $send_params = [
            'statements' => $this->_query_batch
        ];

        $result = $this->_sendNeo4jPostRequest( self::NEO4J_CYPHER_ENDPOINT, json_encode($send_params));
        $result_list = new ResultSet($result);

        if ($this->_config[self::CONFIG_ERROR_MODE] == self::ERROR_MODE_THROW_ERRORS && $result_list->hasError()) {
            throw $result_list->getFirstError();
        }

        return $result_list;
        //print_r($result);exit;
    }

    /**
     * @param string $query
     * @param array $params
     *
     * @return \SimpleNeo4j\ResultSet
     */
    public function executeQuery(string $query, array $params = [])
    {
        $this->addQueryToBatch($query, $params);

        return $this->executeBatchQueries();
    }

    /**
     * @param array $config
     */
    private function _setConfigFromOptions(array $config = [])
    {
        $this->_config = array_merge(self::DEFAULT_CONFIG, $config);

        if (!isset($config['port']) && isset($config['secure'])) {
            $this->_config['port'] = $config['secure'] ? 7473 : 7474;
        }

        if (!isset($config['protocol']) && $this->_config['secure']) {
            $this->_config['protocol'] = 'https';
        }
    }

    /**
     * @return string
     */
    private function _getNeo4jUrl()
    {
        return $this->getProtocol() . '://' . $this->getHost() . ':' . $this->getPort();
    }

    /**
     * @return array
     */
    private function _getRequestHeaders()
    {
        $headers = [
            'Content-type' => 'application/json',
        ];

        if (array_key_exists('username', $this->_config)) {
            $headers['Authorization'] = 'Basic ' . base64_encode($this->_config['username'] . ':' . $this->_config['password']);
        }

        return $headers;
    }

    /**
     * @param string $endpoint
     * @param string $body
     *
     * @return array
     *
     * @throws \SimpleNeo4j\Exception\ConnectionException
     */
    private function _sendNeo4jPostRequest( string $endpoint, string $body )
    {
        $uri = $this->_getNeo4jUrl() . '/' . $endpoint;

        $post_options = [
            'headers' => $this->_getRequestHeaders(),
            'body' => $body,
        ];

        if (isset($this->_config[self::CONFIG_NO_SSL_VERIFY])) {
            $post_options['verify'] = !$this->_config[self::CONFIG_NO_SSL_VERIFY];
        }

        try {
            $result = $this->_http_client->post($uri, $post_options);
            $result = json_decode($result->getBody()->getContents(), true);

            return $result;
        } catch (\GuzzleHttp\Exception\ConnectException $exception) {
            throw new \SimpleNeo4j\Exception\ConnectionException();
        }
    }
}
