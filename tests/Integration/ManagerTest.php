<?php

namespace SimpleNeo4j\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SimpleNeo4j\HttpClient\Client;
use SimpleNeo4j\ORM\Manager;
use SimpleNeo4j\Tests\Fixtures\HasReplay;
use SimpleNeo4j\Tests\Fixtures\Replay;
use SimpleNeo4j\Tests\Fixtures\Session;
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

    public function testCreateNode(): void
    {
        $replay1 = $this->manager->createNode(new Replay([
            'created_time' => 123,
            'sport_id' => 1,
            'real_duration' => 230,
            'predicted_start' => 12,
            'price' => 5,
            'filename' => 'a.mp4',
            'key' => 'abc',
            'thumb_filename' => 'thumb.jpg',
            'neo4j_id' => 7687,
        ]));

        $replay2 = $this->manager->createNode(new Replay([
            'created_time' => 123,
            'sport_id' => 1,
            'real_duration' => 230,
            'predicted_start' => 12,
            'price' => 5,
            'filename' => 'a.mp4',
            'key' => 'abe',
            'thumb_filename' => 'thumb.jpg',
            'neo4j_id' => 7687,
        ]));

        $session = $this->manager->createNode(new Session([
            'key' => 'abd',
            'valid_through' => 123,
        ]));

        $this->manager->createRelationship(new HasReplay($session, $replay1));
        $this->manager->createRelationship(new HasReplay($session, $replay2));

        $node = $this->client->executeQuery('MATCH (x:Replay {key: "abc"}) RETURN x LIMIT 1')->getSingleResult()?->getAsCypherMap(0)->getAsNode('x');

        $OGM = $node->getProperties()->toArray();
        unset($OGM['modified_time']);
        $this->assertEquals([
            'predicted_start' => 12,
            'sport_id' => 1,
            'created_time' => 123,
            'real_duration' => 230,
            'type' => 1,
            'ordered_status' => 0,
            'duration' => 45,
            'thumb_filename' => 'thumb.jpg',
            'filename' => 'a.mp4',
            'price' => 5,
            'state' => 1,
            'id' => 1,
            'key' => 'abc',
        ], $OGM);


        $replay = $this->manager->fetchObjectsByLabelAndProps(Replay::class, ['key' => 'abc']);

        $this->assertCount(1, $replay);

        $count = $this->client->executeQuery('MATCH p = (:Session) - [:HAS_REPLAY] -> (:Replay) RETURN count(p) AS count')->getSingleResult()?->getAsCypherMap(0)->getAsInt('count');

        $this->assertEquals(2, $count);

        $relations = $this->manager->loadRelationsForNode($session, ['_replays']);

        $this->assertCount(2, $relations['_replays']);
    }
}