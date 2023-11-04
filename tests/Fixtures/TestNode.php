<?php

namespace SimpleNeo4j\Tests\Fixtures;

use SimpleNeo4j\ORM\NodeModelAbstract;

class TestNode extends NodeModelAbstract
{
    const ENTITY = 'TestNode';

    public int $id;

    public string $name;

    protected array $_field_info = [
        'id' => [
            'type' => 'int',
        ],
        'name' => [
            'type' => 'string'
        ],
    ];
}