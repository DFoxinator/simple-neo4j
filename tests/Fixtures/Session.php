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

    protected $_facility = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_RELATION,
            ORM\ModelAbstract::PROP_INFO_RELATED_TYPE => HasSession::class,
            ORM\ModelAbstract::PROP_INFO_ENTITY_TYPE => Facility::class,
            ORM\ModelAbstract::PROP_INFO_RELATED_DIRECTION => 'incoming',
            ORM\ModelAbstract::PROP_INFO_DEFAULT => [],
        ]
    ];

    protected $_court = [
        ORM\ModelAbstract::PROP_INFO_KEY => [
            ORM\ModelAbstract::PROP_INFO_TYPE => ORM\ModelAbstract::TYPE_RELATION,
            ORM\ModelAbstract::PROP_INFO_RELATED_TYPE => HasSession::class,
            ORM\ModelAbstract::PROP_INFO_ENTITY_TYPE => Court::class,
            ORM\ModelAbstract::PROP_INFO_RELATED_DIRECTION => 'incoming',
            ORM\ModelAbstract::PROP_INFO_DEFAULT => [],
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

    public function getId(): int
    {
        return $this->_id;
    }


    public function setCourt(?HasSession $has_session)
    {

        $this->_court = $has_session ? [$has_session] : [];

    }

    public function getCourtRel(): ?HasSession
    {
        if (!$this->_court) {
            return null;
        }
        return $this->_court[0] ?? null;
    }

    public function getCourt(): ?Court
    {

        if (!$this->_court) {
            return null;
        }
        return $this->_court[0]?->getStartNode() ?? null;

    }

    public function getCreatedTime(): int
    {

        return $this->_created_time;

    }

    public function getFacility(): Facility
    {

        return $this->_facility[0]->getStartNode();

    }

    public function getName(): string
    {

        return $this->_name;

    }

    public function getState(): int
    {

        return $this->_state;

    }

    public function isActive(): bool
    {

        return $this->getState() === self::STATE_ACTIVE && time() <= $this->_valid_through;

    }

    public function setState(int $state)
    {

        $this->_state = $state;

    }

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

        print_r($replays);
        exit;

    }

    public function getKey(): string
    {

        return $this->_key;

    }
}