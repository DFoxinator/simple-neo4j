<?php

namespace SimpleNeo4j\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SimpleNeo4j\ORM\Manager;

class ManagerTest extends TestCase
{
    use CreatesClientFromEnv;

    private Manager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new Manager($this->createClient());
    }
}