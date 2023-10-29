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
    const CONFIG_PASSWORD = 'password';
    const CONFIG_USERNAME = 'username';

    const DEFAULT_CONFIG = [
        self::CONFIG_HOST => 'localhost',
        self::CONFIG_DATABASE => null,
        self::CONFIG_PORT => null,
        self::CONFIG_SECURE => false,
        self::CONFIG_PROTOCOL => 'neo4j',
        self::CONFIG_ERROR_MODE => self::ERROR_MODE_THROW_ERRORS,
        self::CONFIG_NO_SSL_VERIFY => false,
        self::CONFIG_SHOULD_RETRY_CYPHER_ERRORS => true,
        self::CONFIG_CYPHER_MAX_RETRIES => 40,
        self::CONFIG_CYPHER_RETRY_MAX_INTERVAL_MS => 50,
        self::CONFIG_REQUEST_MAX_RETRIES => 0,
        self::CONFIG_REQUEST_RETRY_INTERVAL_MS => 300,
        self::CONFIG_USERNAME => null,
        self::CONFIG_PASSWORD => null,
    ];

    const RETRYABLE_CYPHER_ERROR_CODES = [
        'Neo.TransientError.Transaction.DeadlockDetected',
        'Neo.TransientError.Transaction.LockAcquisitionTimeout',
        'Neo.TransientError.Transaction.Outdated',
        'Neo.DatabaseError.Transaction.TransactionCommitFailed',
        'Neo.DatabaseError.Statement.ExecutionFailed',
    ];

    /** @var DriverInterface<SummarizedResult> */
    private DriverInterface $driver;

    /**
     * @var array{
     *     host: string,
     *     database: string|null,
     *     port: int|null,
     *     secure: bool,
     *     protocol: string,
     *     error_mode: 'throw'|'hide',
     *     no_ssl_verify: bool,
     *     should_retry_cypher_errors: bool,
     *     cypher_max_retries: int,
     *     cypher_retry_max_interval_ms: int,
     *     request_max_retries: int,
     *     request_retry_interval_ms: int,
     *     username: string|null,
     *     password: string|null
     * }
     */
    private array $_config;

    /**
     * @var list<array{statement: string, parameters: array<string, mixed>}>
     */
    private array $_query_batch = [];

    /**
     * @param array{
     *      host ?: string,
     *      database ?: string|null,
     *      port ?: int|null,
     *      secure ?: bool,
     *      protocol ?: string,
     *      error_mode ?: 'hide'|'throw',
     *      no_ssl_verify ?: bool,
     *      should_retry_cypher_errors ?: bool,
     *      cypher_max_retries ?: int,
     *      cypher_retry_max_interval_ms ?: int,
     *      request_max_retries ?: int,
     *      request_retry_interval_ms ?: int,
     *      username ?: string,
     *      password ?: string
     *  } $config
     * @param DriverInterface<SummarizedResult>|null $driver
     */
    public function __construct(array $config = [], DriverInterface $driver = null)
    {
        $this->_config = array_merge(self::DEFAULT_CONFIG, $config);

        $driverConfig = DriverConfiguration::default();
        $ssl = SslConfiguration::default();

        if ($this->_config[self::CONFIG_SECURE]) {
            $ssl = $ssl->withMode(SslMode::ENABLE());
            if ($this->_config[self::CONFIG_NO_SSL_VERIFY]) {
                $ssl = $ssl->withMode(SslMode::ENABLE_WITH_SELF_SIGNED())
                    ->withVerifyPeer(false);
            }
        }

        /** @psalm-var DriverInterface<SummarizedResult> */
        $this->driver = $driver ?? Driver::create(
            uri: sprintf(
                '%s://%s%s',
                $this->_config[self::CONFIG_PROTOCOL],
                $this->_config[self::CONFIG_HOST],
                $this->_config[self::CONFIG_PORT] ? ':' . $this->_config[self::CONFIG_PORT] : '',
            ),
            configuration: $driverConfig->withSslConfiguration($ssl),
            authenticate: $this->_config[self::CONFIG_USERNAME] && $this->_config[self::CONFIG_PASSWORD] ?
                Authenticate::basic($this->_config[self::CONFIG_USERNAME], $this->_config[self::CONFIG_PASSWORD]) :
                Authenticate::disabled()
        );
    }

    public function getHost(): string
    {
        return $this->_config[self::CONFIG_HOST];
    }

    public function getPort(): int|null
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

    /**
     * @param array<string, mixed> $parameters
     */
    public function addQueryToBatch(string $statement, array $parameters = []): void
    {
        $this->_query_batch[] = compact('statement', 'parameters');
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function prependQueryToBatch(string $statement, array $parameters = []): void
    {
        array_unshift($this->_query_batch, compact('statement', 'parameters'));
    }

    /**
     * @throws CypherQueryException|ConnectionException|Throwable
     */
    public function executeBatchQueries(): ResultSet
    {
        if (!$this->_query_batch) {
            return new ResultSet();
        }

        $num_times_retried = 0;
        $results = [];
        $first_error = null;
        foreach ($this->_query_batch as $query) {
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
        if ($first_error && $this->_config[self::CONFIG_ERROR_MODE] === self::ERROR_MODE_THROW_ERRORS) {
            throw $first_error;
        }

        return new ResultSet($results);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws ConnectionException
     * @throws CypherQueryException
     * @throws Throwable
     */
    public function executeQuery(string $query, array $params = []): ResultSet
    {
        $this->addQueryToBatch($query, $params);

        return $this->executeBatchQueries();
    }

    /**
     * @param array<string, mixed> $parameters
     *
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

                /** @psalm-suppress ArgumentTypeCoercion */
                usleep($min_retry_time_ms * 1000);
            } catch (Neo4jException $exception) {
                return new CypherQueryException(
                    cypherErrorCode: $exception->getNeo4jCode(),
                    message: $exception->getMessage(),
                    previous: $exception
                );
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
