<?php

namespace SimpleNeo4j\HttpClient;

use Bolt\error\ConnectException as BoltConnectException;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Enum\SslMode;
use Laudis\Neo4j\Exception\Neo4jException;
use LogicException;
use SimpleNeo4j\HttpClient\Exception\ConnectionException;
use SimpleNeo4j\HttpClient\Exception\CypherQueryException;
use Throwable;

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
    private array $_config;

    /**
     * @var array
     */
    private array $_query_batch = [];

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

    public function addQueryToBatch(string $query, array $params = []): void
    {
        $params = [
            'statement' => $query,
            'parameters' => (object)$params,
        ];

        $this->_query_batch[] = $params;
    }

    public function prependQueryToBatch(string $query, array $params = [], bool $include_stats = false): void
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
     * @throws CypherQueryException|ConnectionException|Throwable
     */
    public function executeBatchQueries(): ResultSet
    {
        if (!$this->_query_batch) {
            return new ResultSet();
        }

        $this->_query_batch = [];

        $num_times_retried = 0;
        $results = [];
        $first_error = null;

        while ($this->getBatchCount() > 0) {
            $query = array_pop($this->_query_batch);

            do {
                $retry = false;
                $result = $this->runStatement($query['statement'], $query['parameters']);

                if ($result instanceof CypherQueryException) {
                    $first_error ??= $result;
                    if ($this->getShouldRetryCypherErrors()) {
                        if (in_array($result->getCypherErrorCode(), self::RETRYABLE_CYPHER_ERROR_CODES) && $num_times_retried < $this->getCypherMaxRetries()) {
                            ++$num_times_retried;
                            usleep(mt_rand(1, $this->getCypherRetryIntervalMs()) * 1000);
                            $retry = true;
                        }
                    }
                }
            } while ($retry);

            $results[] = $result;
        }

        // For backwards compatibility we will continue the batch even if there is an error and only throw the first
        // error once the batch is finished.
        if ($this->_config[self::CONFIG_ERROR_MODE] === self::ERROR_MODE_THROW_ERRORS) {
            throw $first_error;
        }

        return new ResultSet($results);
    }

    public function executeQuery(string $query, array $params = [], bool $include_stats = false): ResultSet
    {
        $this->addQueryToBatch($query, $params, $include_stats);

        return $this->executeBatchQueries();
    }

    public function callService(string $service, string $body = ''): ?array
    {
        return $this->runStatement($service, $body);
    }

    private function _setConfigFromOptions(array $config = []): void
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
     * @throws ConnectionException|Throwable
     */
    private function runStatement(string $statement, array $parameters): SummarizedResult|CypherQueryException
    {
        $retries_remaining = $this->_config[self::CONFIG_REQUEST_MAX_RETRIES];
        $min_retry_time_ms = $this->_config[self::CONFIG_REQUEST_RETRY_INTERVAL_MS];

        $session = $this->driver->createSession(SessionConfiguration::create(
            $this->_config[self::CONFIG_DATABASE],
        ));

        do {
            try {
                return $session->run($statement, $parameters);
            } catch (BoltConnectException $exception) {
                if ($retries_remaining === 0) {
                    throw new ConnectionException(previous: $exception);
                }

                usleep($min_retry_time_ms * 1000);
            } catch (Neo4jException $exception) {
                $exception = new CypherQueryException(message: $exception->getMessage(), previous: $exception);
                $exception->setCypherErrorCode($exception->getCypherErrorCode());

                return $exception;
            } catch (Throwable $exception) {
                if ($retries_remaining === 0) {
                    throw new $exception;
                }
            }
        } while ($retries_remaining-- > 0);

        // this piece of code is not logically reachable, but we need to satisfy the static analysis.
        throw new LogicException('Could not handle exception in retry logic');
    }
}
