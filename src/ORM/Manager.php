<?php

namespace SimpleNeo4j\ORM;

use SimpleNeo4j\HttpClient;
use SimpleNeo4j\ORM\Exception\ConstraintViolationException;
use SimpleNeo4j\ORM\Exception\ObjectFetchException;

class Manager {

    private $_neo4j_client;

    public function __construct(HttpClient\Client $neo4j_client)
    {
        $this->_neo4j_client = $neo4j_client;
    }

    public function fetchObjectByKey(string $model_class, string $key, $id) : ?ModelAbstract
    {
        $entity = $model_class::ENTITY;

        $query = "MATCH (n:$entity{{$key}:{id}})
                  WITH n, ID(n) as neo4j_id
                  RETURN n{.*, neo4j_id} as info";

        $params = ['id' => $id];

        $result = $this->_neo4j_client->executeQuery($query, $params);

        $result = $result->getSingleResult();

        if (!isset($result[0]['info'])) {
            return null;
        }

        return new $model_class($result[0]['info'], $this);
    }

    public function fetchObjectsByLabel(string $model_class, array $order_by = null, int $limit = null) : array
    {
        $entity = $model_class::ENTITY;

        if ($limit === null && $order_by === null) {
            $query = "MATCH (n:$entity)
                      RETURN COLLECT(n) as info";

            $params = [];
        } elseif ($limit !== null && $order_by === null) {
            $query = "MATCH (n:$entity)
                      WITH n
                      LIMIT {limit}
                      RETURN COLLECT(n) as info";

            $params = [
                'limit' => $limit,
            ];
        } elseif ($order_by !== null && $limit === null) {
            $order_parts = self::getOrderParts($order_by, 'n.');

            $query = "MATCH (n:$entity)
                      WITH n
                      ORDER BY {$order_parts}
                      RETURN COLLECT(n) as info";

            $params = [];
        } else {
            $order_parts = self::getOrderParts($order_by, 'n.');

            $query = "MATCH (n:$entity)
                      WITH n
                      ORDER BY {$order_parts}
                      LIMIT {limit}
                      RETURN COLLECT(n) as info";

            $params = [
                'limit' => $limit,
            ];
        }

        $result = $this->_neo4j_client->executeQuery($query, $params)->getSingleResult();

        if (!isset($result[0]['info']) || !is_array($result[0]['info'])) {
            throw new ObjectFetchException();
        }

        $objects = $model_class::fromDataList($result[0]['info'], $this);
        //print_r($objects);exit;

        return $objects;
        //print_r($result);exit;
    }

    public function fetchObjectsByLabelAndProps(string $model_class, array $props, array $order_by = null, int $limit = null) : array
    {
        $entity = $model_class::ENTITY;

        $props_info = $this->_getPropsInfo($props);
        $where_string = $props_info['where_string'];
        $where_props = $props_info['where_props'];

        if ($limit === null && $order_by === null) {
            $query = "MATCH (n:$entity)
                      $where_string
                      RETURN COLLECT(n) as info";

            $params = $where_props;
        } elseif ($limit !== null && $order_by === null) {
            $query = "MATCH (n:$entity)
                      WITH n
                      LIMIT {limit}
                      RETURN COLLECT(n) as info";

            $params = array_merge($where_props, [
                'limit' => $limit,
            ]);
        } elseif ($order_by !== null && $limit === null) {
            $order_parts = self::getOrderParts($order_by, 'n.');

            $query = "MATCH (n:$entity)
                      WITH n
                      ORDER BY {$order_parts}
                      RETURN COLLECT(n) as info";

            $params = $where_props;
        } else {
            $order_parts = self::getOrderParts($order_by, 'n.');

            $query = "MATCH (n:$entity)
                      WITH n
                      ORDER BY {$order_parts}
                      LIMIT {limit}
                      RETURN COLLECT(n) as info";

            $params = array_merge($where_props, [
                'limit' => $limit,
            ]);
        }

        $result = $this->_neo4j_client->executeQuery($query, $params)->getSingleResult();

        if (!isset($result[0]['info']) || !is_array($result[0]['info'])) {
            throw new ObjectFetchException();
        }

        $objects = $model_class::fromDataList($result[0]['info'], $this);

        return $objects;
    }

    public function createNode(ModelAbstract $object)
    {
        $entity = $object->getEntityType();

        $property_info = $object->getPropertyInfo();

        // regular with auto increment
        $auto_increment_id_field = $property_info['extra'][ModelAbstract::TYPE_AUTO_INCREMENT];
        $query = "
                 MERGE (n:SimpleNeo4jConfig{name:{config_name}})
                 SET n.lock = {lock}
                 WITH n
                 SET n.n = COALESCE(n.n, 0) + 1
                 REMOVE n.lock
                 WITH 
                    n.n as use_id
                 CREATE (n:$entity{{$auto_increment_id_field}:use_id})
                 SET n += {props}
                 WITH n, ID(n) as neo4j_id
                 RETURN n{.*, neo4j_id} as info
                 ";

        $params = [
            'config_name' => 'ai_' . $entity,
            'lock' => 1,
            'props' => $property_info['props'],
        ];

        try {
            $result = $this->_neo4j_client->executeQuery($query, $params);
            $info = $result->getSingleResult()[0]['info'];
            return $object->withProperties($info, $this);
            //print_r($result->getSingleResult());exit;
        } catch (HttpClient\Exception\CypherQueryException $e) {
            if ($e->isConstraintViolation()) {
                throw new ConstraintViolationException();
            }

            throw $e;
        }
    }

    public function createRelationship(RelationshipModelAbstract $relationship)
    {
        $from_node_info = $relationship->getStartNode()->getPropertyInfo();

        $relationship_info = $relationship->getPropertyInfo();

        $to_node_info = $relationship->getEndNode()->getPropertyInfo();

        $n1_primary_field = $from_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY];
        $n2_primary_field = $to_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY];
//print_r($to_node_info);exit;
        $n1_id = $from_node_info['props'][$n1_primary_field];
        $n2_id = $to_node_info['props'][$n2_primary_field];

        $relationship_type = $relationship->getEntityType();

        $from_type = $relationship->getStartNode()->getEntityType();
        $to_type = $relationship->getEndNode()->getEntityType();
        if (isset($relationship_info['extra']['unique']) && $relationship_info['extra']['unique'] === true) {
            $query = "
                     MATCH (n1:{$from_type}{{$n1_primary_field}:{n1_id}}), (n2:{$to_type}{{$n2_primary_field}:{n2_id}})
                     WITH n1, n2
                     MERGE (n1)-[r:{$relationship_type}]->(n2)
                     ON CREATE SET r = {rel_props}
                     WITH r, ID(r) as neo4j_id
                     RETURN r{.*, neo4j_id} as info
                  ";

            $result = $this->_neo4j_client->executeQuery($query, [
                'n1_id' => $n1_id,
                'n2_id' => $n2_id,
                'rel_props' => $relationship_info['props'],
            ]);
        } else {
            $query = "
                     MATCH (n1:Person{id:{n1_id}}), (n2:Person{id:{n2_id}})
                     WITH n1, n2
                     CREATE (n1)-[r:REL]->(n2)
                     SET r = {rel_props}
                  ";
        }

        return $relationship->withProperties($result->getSingleResult()[0]['info']);
    }

    public static function getOrderParts(array $orders, string $prefix = '') : string
    {
        $orders_strings = [];

        foreach ($orders as $order_info) {
            $orders_strings[] = $prefix . $order_info[0] . ' ' . $order_info[1];
        }

        return implode(', ', $orders_strings);
    }

    public function loadRelationsForNode(NodeModelAbstract $node, array $relation_types) {
        $entity = $node->getEntityType();
        $node_id_info = $node->getPrimaryIdInfo();

        $relations_info = $node->getRelationsInfo($relation_types);

        foreach ($relations_info as $relation_name => $relation_info) {
            if  ($relation_info[ModelAbstract::PROP_INFO_RELATED_DIRECTION] === ModelAbstract::PROP_INFO_RELATED_DIRECTION_OUTGOING) {
                $left_arrow = '';
                $right_arrow = '>';
            } else {
                $left_arrow = '<';
                $right_arrow = '';
            }

            $query = '
                  MATCH (n:' . $entity . '{' . $node_id_info['name'] . ':{id}})
                  WITH n
                  MATCH (n)' . $left_arrow . '-[r:' . $relation_info['related_type']::ENTITY . ']-' . $right_arrow . '(other)
                  RETURN other as rel_node, r as rel_rel, {rel_type} as rel_type';

            $params = [
                'rel_type' => $relation_name,
                'id' => $node_id_info['value'],
            ];

            $this->_neo4j_client->addQueryToBatch($query, $params);
        }

        $result = $this->_neo4j_client->executeBatchQueries()->getAllResults();

        $formatted_relations = [];

        foreach ($result as $relation_results) {
            foreach ($relation_results as $relation_result) {
                $relation_info = $relations_info[$relation_result['rel_type']];

                $relationship_type = $relation_info['related_type'];
                $other_node_type = $relation_info['entity_type'];
                $other_node = new $other_node_type($relation_result['rel_node'], $this);

                if ($relations_info[ModelAbstract::PROP_INFO_RELATED_DIRECTION] === ModelAbstract::PROP_INFO_RELATED_DIRECTION_OUTGOING) {
                    $relationship = new $relationship_type($node, $other_node, $relation_result['rel_rel']);
                } else {
                    $relationship = new $relationship_type($other_node, $node, $relation_result['rel_rel']);
                }

                if (!isset($formatted_relations[$relation_result['rel_type']])) {
                    $formatted_relations[$relation_result['rel_type']] = [];
                }
                $formatted_relations[$relation_result['rel_type']][] = $relationship;
            }
        }

        return $formatted_relations;

    }

    public function saveNode(NodeModelAbstract $node) : NodeModelAbstract
    {
        $modified_properties = $node->getModifiedProperties();

        if (!$modified_properties) {
            return $node;
        }


        $primary_id_info = $node->getPrimaryIdInfo();
        $model_class = get_class($node);

        $entity = $node::ENTITY;

        $key = $primary_id_info['name'];

        $query = "MATCH (n:$entity{{$key}:{id}})
                  WITH n
                  SET n += {props}
                  WITH n, ID(n) as neo4j_id
                  RETURN n{.*, neo4j_id} as info";

        $params = ['id' => $primary_id_info['value'], 'props' => $modified_properties];

        $result = $this->_neo4j_client->executeQuery($query, $params);

        $result = $result->getSingleResult();

        if (!isset($result[0]['info'])) {
            return null;
        }

        return new $model_class($result[0]['info'], $this);

    }

    public function deleteNode(NodeModelAbstract $node)
    {
        $primary_id_info = $node->getPrimaryIdInfo();

        $entity = $node::ENTITY;

        $key = $primary_id_info['name'];

        $query = "MATCH (n:$entity{{$key}:{id}})
                  WITH n
                  DETACH DELETE n";

        $params = ['id' => $primary_id_info['value']];

        $result = $this->_neo4j_client->executeQuery($query, $params);
    }

    public function saveRelationship(RelationshipModelAbstract $relationship)
    {
        $modified_properties = $relationship->getModifiedProperties();

        if (!$modified_properties) {
            return $relationship;
        }

        $from_node_info = $relationship->getStartNode()->getPropertyInfo();
        $to_node_info = $relationship->getEndNode()->getPropertyInfo();

        $n1_primary_field = $from_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY];
        $n2_primary_field = $to_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY];

        $n1_id = $from_node_info['props'][$n1_primary_field];
        $n2_id = $to_node_info['props'][$n2_primary_field];

        $relationship_type = $relationship->getEntityType();

        $from_type = $relationship->getStartNode()->getEntityType();
        $to_type = $relationship->getEndNode()->getEntityType();

        $query = "
                 MATCH (n1:{$from_type}{{$n1_primary_field}:{n1_id}}), (n2:{$to_type}{{$n2_primary_field}:{n2_id}})
                 WITH n1, n2
                 MATCH (n1)-[r:{$relationship_type}]->(n2)
                 WHERE ID(r)={relationship_neo4j_id}
                 WITH r
                 SET r += {props}
                 WITH r, ID(r) as neo4j_id
                 RETURN r{.*, neo4j_id} as info
              ";

        $result = $this->_neo4j_client->executeQuery($query, [
            'n1_id' => $n1_id,
            'n2_id' => $n2_id,
            'relationship_neo4j_id' => $relationship->getNeo4jId(),
            'props' => $modified_properties,
        ]);

        return $relationship->withProperties($result->getSingleResult()[0]['info']);
    }

    public function deleteRelationship(RelationshipModelAbstract $relationship)
    {
        $from_node_info = $relationship->getStartNode()->getPropertyInfo();
        $to_node_info = $relationship->getEndNode()->getPropertyInfo();

        $n1_primary_field = $from_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY];
        $n2_primary_field = $to_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY];

        $n1_id = $from_node_info['props'][$n1_primary_field];
        $n2_id = $to_node_info['props'][$n2_primary_field];

        $relationship_type = $relationship->getEntityType();

        $from_type = $relationship->getStartNode()->getEntityType();
        $to_type = $relationship->getEndNode()->getEntityType();

        $query = "
                 MATCH (n1:{$from_type}{{$n1_primary_field}:{n1_id}}), (n2:{$to_type}{{$n2_primary_field}:{n2_id}})
                 WITH n1, n2
                 MATCH (n1)-[r:{$relationship_type}]->(n2)
                 WHERE ID(r)={relationship_neo4j_id}
                 WITH r
                 DELETE r
              ";

        $result = $this->_neo4j_client->executeQuery($query, [
            'n1_id' => $n1_id,
            'n2_id' => $n2_id,
            'relationship_neo4j_id' => $relationship->getNeo4jId(),
        ]);
    }

    private function _getPropsInfo(array $props) : array
    {
        $where_parts = [];
        $where_props = [];

        foreach ($props as $prop_name => $prop_value) {
            $where_parts[] = 'n.' . $prop_name . '={n_' . $prop_name . '}';
            $where_props['n_' . $prop_name] = $prop_value;
        }

        return [
            'where_string' => $where_parts ? ('WHERE ' . implode(' AND ', $where_parts)) : '',
            'where_props' => $where_props,
        ];
    }

}
