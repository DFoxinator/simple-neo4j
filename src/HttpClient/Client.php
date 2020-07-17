<?php

namespace SimpleNeo4j\HttpClient;

class Client
{
    const CONFIG_HOST = 'host';
    const CONFIG_PORT = 'port';
    const CONFIG_SECURE = 'secure';
    const CONFIG_PROTOCOL = 'protocol';
    const CONFIG_ERROR_MODE = 'error_mode';
    const CONFIG_NO_SSL_VERIFY = 'no_ssl_verify';
    const CONFIG_SHOULD_RETRY_CYPHER_ERRORS = 'should_retry_cypher_errors';
    const CONFIG_CYPHER_MAX_RETRIES = 'cypher_max_retries';
    const CONFIG_CYPHER_RETRY_MAX_INTERVAL_MS = 'cypher_retry_max_interval_ms';

    const ERROR_MODE_HIDE_ERRORS = 'hide';
    const ERROR_MODE_THROW_ERRORS = 'throw';

    const NEO4J_CYPHER_ENDPOINT = 'db/data/transaction/commit';

    const DEFAULT_CONFIG = [
        self::CONFIG_HOST => 'localhost',
        self::CONFIG_PORT => 7474,
        self::CONFIG_SECURE => false,
        self::CONFIG_PROTOCOL => 'http',
        self::CONFIG_ERROR_MODE => self::ERROR_MODE_THROW_ERRORS,
        self::CONFIG_NO_SSL_VERIFY => false,
        self::CONFIG_SHOULD_RETRY_CYPHER_ERRORS => true,
        self::CONFIG_CYPHER_MAX_RETRIES => 40,
        self::CONFIG_CYPHER_RETRY_MAX_INTERVAL_MS => 50,
    ];

    const RETRYABLE_CYPHER_ERROR_CODES = [
        'Neo.TransientError.Transaction.DeadlockDetected',
        'Neo.TransientError.Transaction.LockAcquisitionTimeout',
        'Neo.TransientError.Transaction.Outdated',
        'Neo.DatabaseError.Transaction.TransactionCommitFailed',
        'Neo.DatabaseError.Statement.ExecutionFailed',
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

    public function __construct(array $config = [], \GuzzleHttp\Client $http_client = null)
    {
        $this->_setConfigFromOptions($config);
        $this->_http_client = $http_client ?: new \GuzzleHttp\Client();
    }

    public function getHost() : string
    {
        return $this->_config[self::CONFIG_HOST];
    }

    public function getPort() : int
    {
        return $this->_config[self::CONFIG_PORT];
    }

    public function isSecure() : bool
    {
        return $this->_config[self::CONFIG_SECURE];
    }

    public function getProtocol() : string
    {
        return $this->_config[self::CONFIG_PROTOCOL];
    }

    public function getShouldRetryCypherErrors() : bool
    {
        return $this->_config[self::CONFIG_SHOULD_RETRY_CYPHER_ERRORS];
    }

    public function getCypherMaxRetries() : int
    {
        return $this->_config[self::CONFIG_CYPHER_MAX_RETRIES];
    }

    public function getCypherRetryIntervalMs() : int
    {
        return $this->_config[self::CONFIG_CYPHER_RETRY_MAX_INTERVAL_MS];
    }

    public function getBatchCount() : int {

        return count($this->_query_batch);

    }

    public function addQueryToBatch(string $query, array $params = [], bool $include_stats = false)
    {
        $params = [
            'statement' => $query,
            'parameters' => (object)$params,
        ];

        if ($include_stats) {
            $params['includeStats'] = true;
        }

        $this->_query_batch[] = $params;
    }

    public function prependQueryToBatch(string $query, array $params = [], bool $include_stats = false)
    {
        $params = [
            'statement' => $query,
            'parameters' => (object)$params,
        ];

        if ($include_stats) {
            $params['includeStats'] = true;
        }

        array_unshift($this->_query_batch, $params);
    }

    /**
     * @throws \SimpleNeo4j\HttpClient\Exception\CypherQueryException
     */
    public function executeBatchQueries() : ResultSet
    {
        if (!$this->_query_batch) {
            return new ResultSet();
        }

        $send_params = [
            'statements' => $this->_query_batch
        ];

        $this->_query_batch = [];

        $execute_batch = true;

        $num_times_retried = 0;

        while ($execute_batch) {
            $result = $this->_sendNeo4jPostRequest(self::NEO4J_CYPHER_ENDPOINT, json_encode($send_params));
            $result_list = new ResultSet($result);

            if ($result_list->hasError()) {
                $first_error = $result_list->getFirstError();

                if ($this->getShouldRetryCypherErrors()) {
                    if (in_array($first_error->getCypherErrorCode(), self::RETRYABLE_CYPHER_ERROR_CODES) && $num_times_retried < $this->getCypherMaxRetries()) {
                        ++$num_times_retried;
                        usleep(mt_rand(1, $this->getCypherRetryIntervalMs()) * 1000);
                        continue;
                    }
                }

                $execute_batch = false;

                if ($this->_config[self::CONFIG_ERROR_MODE] == self::ERROR_MODE_THROW_ERRORS) {
                    throw $first_error;
                }
            } else {
                $execute_batch = false;
            }
        }

        return $result_list;

    }

    public function executeQuery(string $query, array $params = [], bool $include_stats = false) : ResultSet
    {
        $this->addQueryToBatch($query, $params, $include_stats);

        return $this->executeBatchQueries();
    }

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

    private function _getNeo4jUrl() : string
    {
        return $this->getProtocol() . '://' . $this->getHost() . ':' . $this->getPort();
    }

    private function _getRequestHeaders() : array
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
     * @throws \SimpleNeo4j\HttpClient\Exception\ConnectionException
     */
    private function _sendNeo4jPostRequest( string $endpoint, string $body ) : array
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
            throw new Exception\ConnectionException();
        }
    }
}
