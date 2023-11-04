<?php

namespace SimpleNeo4j\Tests\Integration;


class ClientIntegrationTest extends AbstractClientIntegration
{
    public function testAcceptanceExecuteQuery(): void
    {
        $result = $this->client->executeQuery('RETURN 1 AS x');

        $this->assertEquals(1, $result->getSingleResult()?->getAsMap(0)->get('x'));
    }

    /**
     * @psalm-suppress PossiblyUndefinedIntArrayOffset
     */
    public function testQueryBatchingAcceptance(): void
    {
        $this->assertEquals(0, $this->client->getBatchCount());

        $this->client->addQueryToBatch('RETURN 1 AS x');
        $this->client->addQueryToBatch('RETURN 4 AS x');
        $this->client->addQueryToBatch('RETURN 8 AS y');

        $this->assertEquals(3, $this->client->getBatchCount());

        $results = $this->client->executeBatchQueries()->getAllResults();

        $this->assertEquals(0, $this->client->getBatchCount());

        $this->assertCount(3, $results);
        $this->assertEquals(1, $results[0]->getAsMap(0)->get('x'));
        $this->assertEquals(4, $results[1]->getAsMap(0)->get('x'));
        $this->assertEquals(8, $results[2]->getAsMap(0)->get('y'));
    }
}