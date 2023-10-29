<?php

namespace SimpleNeo4j\ORM;

abstract class RelationshipModelAbstract extends ModelAbstract {

    protected $_manager;

    public function __construct(
        protected NodeModelAbstract $_start_node,
        protected NodeModelAbstract $_end_node,
        array $props = [],
        Manager $manager = null
    ) {
        $this->_manager = $manager;

        parent::__construct($props, $manager);
    }

    public function withProperties(array $properties) : ModelAbstract
    {
        return new $this($this->_start_node, $this->_end_node, $properties, $this->_manager);
    }

    public function getStartNode() : NodeModelAbstract
    {
        return $this->_start_node;
    }

    public function getEndNode() : NodeModelAbstract
    {
        return $this->_end_node;
    }

}
