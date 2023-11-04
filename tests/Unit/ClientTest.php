<?php

namespace SimpleNeo4j\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SimpleNeo4j\HttpClient\Client;

class ClientTest extends TestCase
{
    public function testClientCreationBasic(): void
    {
        $config = [
            'host' => '127.0.0.1',
            'username' => 'myuser',
            'password' => 'mypw',
        ];

        $client = new Client( $config );

        $this->assertEquals('127.0.0.1', $client->getHost());
        $this->assertNull($client->getPort());
        $this->assertEquals('neo4j', $client->getProtocol());
        $this->assertFalse($client->isSecure());
    }

    public function testClientCreationSecureNoPort(): void
    {
        $config = [
            'host' => '127.0.0.1',
            'username' => 'myuser',
            'password' => 'mypw',
            'secure' => true,
        ];

        $client = new Client( $config );

        $this->assertNull($client->getPort());
        $this->assertEquals('neo4j', $client->getProtocol());
        $this->assertTrue($client->isSecure());
    }

    public function testClientCreationNotSecurePort(): void
    {
        $config = [
            'host' => '127.0.0.1',
            'username' => 'myuser',
            'password' => 'mypw',
            'secure' => false,
            'port' => 8888,
        ];

        $client = new Client( $config );

        $this->assertEquals(8888, $client->getPort());
        $this->assertEquals('neo4j', $client->getProtocol());
        $this->assertFalse($client->isSecure());
    }

    public function testClientCreationSecurePort(): void
    {
        $config = [
            'host' => '127.0.0.1',
            'username' => 'myuser',
            'password' => 'mypw',
            'secure' => true,
            'port' => 9999,
        ];

        $client = new Client( $config );

        $this->assertEquals(9999, $client->getPort());
        $this->assertEquals('neo4j', $client->getProtocol());
        $this->assertTrue($client->isSecure());
    }
}
