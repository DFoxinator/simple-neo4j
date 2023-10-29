<?php

namespace SimpleNeo4j\HttpClient;

use GuzzleHttp\Exception\RequestException;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;

class Client
{
    const CONFIG_HOST = 'host';
    const CONFIG_DATABASE = 'database';
    const CONFIG_PORT = 'port';
    const CONFIG_SECURE = 'secure';
    const CONFIG_PROTOCOL = 'protocol';
    const CONFIG_ERROR_MODE = 'error_mode';
    const CONFIG_NO_SSL_VERIFY = 'no_ssl_verify';
    const CONFIG_SHOULD_RETRY_CYPHER_ERRORS = 'should_retry_cypher_errors';
    const CONFIG_CYPHER_MAX_RETRIES = 'cypher_max_retries';
    const CONFIG_CYPHER_RETRY_MAX_INTERVAL_MS = 'cypher_retry_max_interval_ms';
    const CONFIG_REQUEST_MAX_RETRIES = 'request_max_retries';
    const CONFIG_REQUEST_RETRY_INTERVAL_MS = 'request_retry_interval_ms';

    const ERROR_MODE_HIDE_ERRORS = 'hide';
    const ERROR_MODE_THROW_ERRORS = 'throw';

    const DEFAULT_CONFIG = [
        self::CONFIG_HOST => 'localhost',
        self::CONFIG_DATABASE => null,
        self::CONFIG_PORT => 7474,
        self::CONFIG_SECURE => false,
        self::CONFIG_PROTOCOL => 'http',
        self::CONFIG_ERROR_MODE => self::ERROR_MODE_THROW_ERRORS,
        self::CONFIG_NO_SSL_VERIFY => false,
        self::CONFIG_SHOULD_RETRY_CYPHER_ERRORS => true,
        self::CONFIG_CYPHER_MAX_RETRIES => 40,
        self::CONFIG_CYPHER_RETRY_MAX_INTERVAL_MS => 50,
        self::CONFIG_REQUEST_MAX_RETRIES => 0,
        self::CONFIG_REQUEST_RETRY_INTERVAL_MS => 300,
    ];

    const RETRYABLE_CYPHER_ERROR_CODES = [
        'Neo.TransientError.Transaction.DeadlockDetected',
        'Neo.TransientError.Transaction.LockAcquisitionTimeout',
        'Neo.TransientError.Transaction.Outdated',
        'Neo.DatabaseError.Transaction.TransactionCommitFailed',
        'Neo.DatabaseError.Statement.ExecutionFailed',
    ];

    private DriverInterface $driver;

    /**
     * @var array
     */
    private $_config;

    /**
     * @var array
     */
    private $_query_batch = [];

    public function __construct(array $config = [], DriverInterface $driver = null)
    {
        $this->_setConfigFromOptions($config);

        $driverConfig = DriverConfiguration::default();
        $ssl = SslConfiguration::default();

        if ($this->_config[self::CONFIG_SECURE]) {
            $ssl = $ssl->withMode(SslMode::ENABLE());
            if ($this->_config[self::CONFIG_NO_SSL_VERIFY]) {
                $ssl = $ssl->withMode(SslMode::ENABLE_WITH_SELF_SIGNED())
                    ->withVerifyPeer(false);
            }
        }

        $this->driver = $driver ?? Driver::create(
            uri: sprintf(
                '%s://%s:%s',
                $this->_config[self::CONFIG_PROTOCOL],
                $this->_config['host'],
                $this->_config['port']
            ),
            configuration: $driverConfig->withSslConfiguration($ssl),
            authenticate: ($config['username'] ?? false) && ($config['password'] ?? false) ?
                Authenticate::basic($config['username'], $config['password']) :
                Authenticate::disabled()
        );
    }

    public function getHost(): string
    {
        return $this->_config[self::CONFIG_HOST];
    }

    public function getPort(): int
    {
        return $this->_config[self::CONFIG_PORT];
    }

    public function isSecure(): bool
    {
        return $this->_config[self::CONFIG_SECURE];
    }

    public function getProtocol(): string
    {
        return $this->_config[self::CONFIG_PROTOCOL];
    }

    public function getShouldRetryCypherErrors(): bool
    {
        return $this->_config[self::CONFIG_SHOULD_RETRY_CYPHER_ERRORS];
    }

    public function getCypherMaxRetries(): int
    {
        return $this->_config[self::CONFIG_CYPHER_MAX_RETRIES];
    }

    public function getCypherRetryIntervalMs(): int
    {
        return $this->_config[self::CONFIG_CYPHER_RETRY_MAX_INTERVAL_MS];
    }

    public function getBatchCount(): int
    {
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
    public function executeBatchQueries(): ResultSet
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
            $result = $this->_sendNeo4jPostRequest($this->getCypherEndpoint(), json_encode($send_params));
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

    public function executeQuery(string $query, array $params = [], bool $include_stats = false): ResultSet
    {
        $this->addQueryToBatch($query, $params, $include_stats);

        return $this->executeBatchQueries();
    }

    public function callService(string $service, string $body = ''): ?array
    {

        return $this->_sendNeo4jPostRequest($service, $body);

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

    /**
     * @throws \SimpleNeo4j\HttpClient\Exception\ConnectionException
     */
    private function _sendNeo4jPostRequest(string $statement, array $parameters, bool $includeStats): ?array
    {
        $uri = $this->_getNeo4jUrl() . '/' . $endpoint;

        $post_options = [
            'headers' => $this->_getRequestHeaders(),
            'body' => $body,
        ];

        if (isset($this->_config[self::CONFIG_NO_SSL_VERIFY])) {
            $post_options['verify'] = !$this->_config[self::CONFIG_NO_SSL_VERIFY];
        }

        $retries_remaining = $this->_config[self::CONFIG_REQUEST_MAX_RETRIES];
        $min_retry_time_ms = $this->_config[self::CONFIG_REQUEST_RETRY_INTERVAL_MS];

        do {
            try {
                $result = $this->_http_client->post($uri, $post_options);
                $result = json_decode($result->getBody()->getContents(), true);

                return $result;
            } catch (\GuzzleHttp\Exception\ConnectException|RequestException $exception) {
                if ($retries_remaining === 0 || ($exception instanceof RequestException && $exception->getCode() != 503)) {
                    throw new Exception\ConnectionException();
                }

                usleep($min_retry_time_ms * 1000);
            }
        } while ($retries_remaining-- > 0);
    }
}
