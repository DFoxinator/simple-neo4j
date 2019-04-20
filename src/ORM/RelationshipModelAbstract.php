<?php

namespace SimpleNeo4j\ORM;

abstract class RelationshipModelAbstract extends ModelAbstract {

    protected $_start_node;
    protected $_end_node;
    protected $_manager;

    public function __construct(NodeModelAbstract $start_node, NodeModelAbstract $end_node, array $props = [], Manager $manager = null)
    {
        $this->_start_node = $start_node;
        $this->_end_node = $end_node;
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
