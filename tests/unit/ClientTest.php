<?php

namespace SimpleNeo4j\Tests;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testClientCreationBasic() {
        $client = new \SimpleNeo4j\Client( '127.0.0.1', 'myuser', 'mypw' );

        $this->assertEquals('127.0.0.1', $client->getHost());
        $this->assertEquals(7474, $client->getPort());
        $this->assertEquals('http', $client->getProtocol());
        $this->assertFalse($client->isSecure());
    }

    public function testClientCreationSecureNoPort() {
        $client = new \SimpleNeo4j\Client( '127.0.0.1', 'myuser', 'mypw', true );

        $this->assertEquals(7473, $client->getPort());
        $this->assertEquals('https', $client->getProtocol());
        $this->assertTrue($client->isSecure());
    }

    public function testClientCreationNotSecurePort() {
        $client = new \SimpleNeo4j\Client( '127.0.0.1', 'myuser', 'mypw', false, 8888 );

        $this->assertEquals(8888, $client->getPort());
        $this->assertEquals('http', $client->getProtocol());
        $this->assertFalse($client->isSecure());
    }

    public function testClientCreationSecurePort() {
        $client = new \SimpleNeo4j\Client( '127.0.0.1', 'myuser', 'mypw', true, 9999 );

        $this->assertEquals(9999, $client->getPort());
        $this->assertEquals('https', $client->getProtocol());
        $this->assertTrue($client->isSecure());
    }
}
