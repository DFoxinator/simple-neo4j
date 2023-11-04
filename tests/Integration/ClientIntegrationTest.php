<?php

namespace SimpleNeo4j\Tests\Integration;

use SimpleNeo4j\HttpClient\Client;

class ClientIntegrationTest
{
    private Client $client;

    public function setUp(): void
    {
        $this->client = new Client([
            'host' => $_ENV['NEO4J_HOST'] ?? null,
            'database' => $_ENV['NEO4J_DATABASE'] ?? null,
            'port' => $_ENV['NEO4J_PORT'] ?? null,
            'secure' => $_ENV['NEO4J_SECURE'] ?? null,
            'protocol'=> $_ENV['NEO4J_PROTOCOL'] ?? null,
            'error_mode' => $_ENV['NEO4J_ERROR_MODE'] ?? null,
            'no_ssl_verify' => $_ENV['NEO4J_NO_SSL_VERIFY'] ?? null,
            'should_retry_cypher_errors' => $_ENV['NEO4J_SHOULD_RETRY_CYPHER_ERRORS'] ?? null,
            'cypher_max_retries' => $_ENV['NEO4J_CYPHER_MAX_RETRIES'] ?? null,
            'cypher_retry_max_interval_ms' => $_ENV['NEO4J_CYPHER_RETRY_MAX_INTERVAL_MS'] ?? null,
            'request_max_retries' => $_ENV['NEO4J_REQUEST_MAX_RETRIES'] ?? null,
            'request_retry_interval_ms' => $_ENV['NEO4J_REQUEST_RETRY_INTERVAL_MS'] ?? null,
            'username' => $_ENV['NEO4J_USERNAME'] ?? null,
            'password' => $_ENV['NEO4J_PASSWORD'] ?? null,
        ]);
    }
}