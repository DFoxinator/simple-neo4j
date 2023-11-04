<?php

namespace SimpleNeo4j\ORM;

abstract class RelationshipModelAbstract extends ModelAbstract
{
    /**
     * @param array<string, mixed> $props
     */
    public function __construct(
        protected NodeModelAbstract $_start_node,
        protected NodeModelAbstract $_end_node,
        array                       $props = [],
        Manager|null                $manager = null
    )
    {
        parent::__construct($props, $manager);
    }

    public function withProperties(array $properties): static
    {
        return new $this($this->_start_node, $this->_end_node, $properties, $this->manager);
    }

    public function getStartNode(): NodeModelAbstract
    {
        return $this->_start_node;
    }

    public function getEndNode(): NodeModelAbstract
    {
        return $this->_end_node;
    }
}
