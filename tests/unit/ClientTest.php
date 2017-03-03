<?php

namespace SimpleNeo4j\Tests;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testClientCreationBasic() {
        $config = [
            'host' => '127.0.0.1',
            'username' => 'myuser',
            'password' => 'mypw',
        ];

        $client = new \SimpleNeo4j\Client( $config );

        $this->assertEquals('127.0.0.1', $client->getHost());
        $this->assertEquals(7474, $client->getPort());
        $this->assertEquals('http', $client->getProtocol());
        $this->assertFalse($client->isSecure());
    }

    public function testClientCreationSecureNoPort() {
        $config = [
            'host' => '127.0.0.1',
            'username' => 'myuser',
            'password' => 'mypw',
            'secure' => true,
        ];

        $client = new \SimpleNeo4j\Client( $config );

        $this->assertEquals(7473, $client->getPort());
        $this->assertEquals('https', $client->getProtocol());
        $this->assertTrue($client->isSecure());
    }

    public function testClientCreationNotSecurePort() {
        $config = [
            'host' => '127.0.0.1',
            'username' => 'myuser',
            'password' => 'mypw',
            'secure' => false,
            'port' => 8888,
        ];

        $client = new \SimpleNeo4j\Client( $config );

        $this->assertEquals(8888, $client->getPort());
        $this->assertEquals('http', $client->getProtocol());
        $this->assertFalse($client->isSecure());
    }

    public function testClientCreationSecurePort() {
        $config = [
            'host' => '127.0.0.1',
            'username' => 'myuser',
            'password' => 'mypw',
            'secure' => true,
            'port' => 9999,
        ];

        $client = new \SimpleNeo4j\Client( $config );

        $this->assertEquals(9999, $client->getPort());
        $this->assertEquals('https', $client->getProtocol());
        $this->assertTrue($client->isSecure());
    }
}
