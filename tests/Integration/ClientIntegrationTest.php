<?php

namespace SimpleNeo4j\Tests\Integration;


class ClientIntegrationTest extends AbstractClientIntegration
{
    public function testAcceptance(): void
    {
        $result = $this->client->executeQuery('RETURN 1 AS x');

        $this->assertEquals(1, $result->getSingleResult()?->getAsMap(0)->get('x'));
    }
}