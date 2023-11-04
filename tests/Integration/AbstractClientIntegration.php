<?php

namespace SimpleNeo4j\Tests\Integration;

use Exception;
use PHPUnit\Framework\TestCase;
use SimpleNeo4j\HttpClient\Client;

/**
 * @psalm-suppress MissingConstructor
 */
abstract class AbstractClientIntegration extends TestCase
{
    protected Client $client;

    public function setUp(): void
    {
        $errorMode = array_key_exists('NEO4J_ERROR_MODE', $_ENV) ? $_ENV['NEO4J_ERROR_MODE'] : null;
        if ($errorMode !== null && $errorMode !== 'hide' && $errorMode !== 'throw') {
            throw new Exception('Invalid error mode: ' . $errorMode . '. Valid values are: "hide", "throw" and null');
        }

        $this->client = new Client([
            'host' => array_key_exists('NEO4J_HOST', $_ENV) ? $_ENV['NEO4J_HOST'] : null,
            'database' => array_key_exists('NEO4J_DATABASE', $_ENV) ? $_ENV['NEO4J_DATABASE'] : null,
            'port' => array_key_exists('NEO4J_PORT', $_ENV) ? (int) $_ENV['NEO4J_PORT'] : null,
            'secure' => array_key_exists('NEO4J_SECURE', $_ENV) ? (bool) $_ENV['NEO4J_SECURE'] : null,
            'protocol'=> array_key_exists('NEO4J_PROTOCOL', $_ENV) ? $_ENV['NEO4J_PROTOCOL'] : null,
            'error_mode' => $errorMode,
            'no_ssl_verify' => array_key_exists('NEO4J_NO_SSL_VERIFY', $_ENV) ? (bool) $_ENV['NEO4J_NO_SSL_VERIFY'] : null,
            'should_retry_cypher_errors' => array_key_exists('NEO4J_SHOULD_RETRY_CYPHER_ERRORS', $_ENV) ? (bool) $_ENV['NEO4J_SHOULD_RETRY_CYPHER_ERRORS'] : null,
            'cypher_max_retries' => array_key_exists('NEO4J_CYPHER_MAX_RETRIES', $_ENV) ? (int) $_ENV['NEO4J_CYPHER_MAX_RETRIES'] : null,
            'cypher_retry_max_interval_ms' => array_key_exists('NEO4J_CYPHER_RETRY_MAX_INTERVAL_MS', $_ENV) ? (int) $_ENV['NEO4J_CYPHER_RETRY_MAX_INTERVAL_MS'] : null,
            'request_max_retries' => array_key_exists('NEO4J_REQUEST_MAX_RETRIES', $_ENV) ? (int) $_ENV['NEO4J_REQUEST_MAX_RETRIES'] : null,
            'request_retry_interval_ms' => array_key_exists('NEO4J_REQUEST_RETRY_INTERVAL_MS', $_ENV) ? (int) $_ENV['NEO4J_REQUEST_RETRY_INTERVAL_MS'] : null,
            'username' => array_key_exists('NEO4J_USERNAME', $_ENV) ? $_ENV['NEO4J_USERNAME'] : null,
            'password' => array_key_exists('NEO4J_PASSWORD', $_ENV) ? $_ENV['NEO4J_PASSWORD'] : null,
        ]);
    }
}