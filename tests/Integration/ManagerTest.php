<?php

namespace SimpleNeo4j\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SimpleNeo4j\HttpClient\Client;
use SimpleNeo4j\ORM\Manager;
use SimpleNeo4j\Tests\Fixtures\TestNode;

class ManagerTest extends TestCase
{
    use CreatesClientFromEnv;

    private Manager $manager;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createClient();
        $this->manager = new Manager($this->client);

        $this->client->executeQuery('MATCH (x) DETACH DELETE x');
    }

    public function testFetchObjectByKey(): void
    {
        $this->client->executeQuery(<<<'CYPHER'
        UNWIND range(1, 10) AS x
        CREATE (n:TestNode {name: 'test', id: x})
        CYPHER);

        $node = $this->manager->fetchObjectByKey(TestNode::class, 'id', 5);
        $this->assertNotNull($node);
        $this->assertEquals(5, $node->id);

        $node = $this->manager->fetchObjectByKey(TestNode::class, 'id', 12);
        $this->assertNull($node);
    }
}