<?php

namespace SimpleNeo4j\Tests\Fixtures;

use SimpleNeo4j\ORM;

class Session extends ORM\NodeModelAbstract
{
    const ENTITY = 'Session';

    const STATE_ACTIVE = 1;

    const STATE_DONE = 2;

    const STATE_CLOSED = 3;

    protected $_id = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_PRIMARY => true,
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_AUTO_INCREMENT,
        ]
    ];

    protected $_state = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => self::STATE_ACTIVE,
        ]
    ];

    protected $_key = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_STRING,
        ]
    ];

    protected $_valid_through = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
        ]
    ];

    protected $_created_time = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_CREATED_ON,
        ]
    ];

    protected $_modified_time = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_MODIFIED_ON,
        ]
    ];

    protected $_last_replay_saved_time = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_INTEGER,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => null,
        ]
    ];

    protected $_is_free = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_BOOLEAN,
            ORM\ModelAbstract::PROP_INFO_DEFAULT => false,
        ]
    ];


    protected $_replays = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_RELATION,
            ORM\ModelAbstract::PROP_INFO_RELATED_TYPE => HasReplay::class,
            ORM\ModelAbstract::PROP_INFO_ENTITY_TYPE => Replay::class,
            ORM\ModelAbstract::PROP_INFO_RELATED_DIRECTION => 'outgoing',
            ORM\ModelAbstract::PROP_INFO_DEFAULT => [],
        ]
    ];

    protected bool $_is_limited_public_session = false;

    public function getReplays(bool $include_only_purchased = false): array
    {

        /** @var HasReplay[] $replay_rels */
        $replay_rels = $this->_replays;

        $replays = [];

        foreach ($replay_rels as $replay_rel) {
            /** @var Replay $replay */
            $replay = $replay_rel->getEndNode();
            if ($include_only_purchased && !$replay->isPurchased()) {
                continue;
            }
            $replays[] = $replay;
        }

        usort($replays, fn(Replay $a, Replay $b) => $a->getId() <=> $b->getId());

        return array_values($replays);
    }
}