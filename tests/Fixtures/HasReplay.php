<?php

namespace SimpleNeo4j\Tests\Fixtures;

use SimpleNeo4j\ORM;

class HasReplay extends ORM\RelationshipModelAbstract {

    const ENTITY = 'HAS_REPLAY';

    const IS_UNIQUE = true;

    protected $_created_time = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_CREATED_ON,
        ]
    ];
}