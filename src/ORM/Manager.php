<?php

namespace SimpleNeo4j\ORM;

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Map;
use Laudis\Neo4j\Types\Node;
use LogicException;
use SimpleNeo4j\HttpClient;
use SimpleNeo4j\ORM\Exception\ConstraintViolationException;

/**
 * @psalm-suppress UnsafeInstantiation
 *
 * @psalm-import-type FieldInfo from ModelAbstract
 */
class Manager
{
    private HttpClient\Client $_neo4j_client;

    public function __construct(HttpClient\Client $neo4j_client)
    {
        $this->_neo4j_client = $neo4j_client;
    }

    /**
     * @param class-string<ModelAbstract> $model_class
     */
    public function fetchObjectByKey(string $model_class, string $key, mixed $id): ?ModelAbstract
    {
        /** @var string */
        $entity = $model_class::ENTITY;

        $query = "MATCH (n:$entity{{$key}:\$id})
                  WITH n, ID(n) as neo4j_id
                  RETURN n{.*, neo4j_id} as info";

        $params = ['id' => $id];

        $result = $this->execute($query, $params, true);

        return new $model_class($result->getAsMap(0)->getAsMap('info')->toArray(), $this);
    }

    /**
     * @param class-string<ModelAbstract> $model_class
     */
    public function fetchObjectsByKeys(string $model_class, string $key, array $ids): array
    {
        /** @var string */
        $entity = $model_class::ENTITY;

        $query = "MATCH (n:$entity)
                  WHERE n.{$key} IN \$ids
                  WITH n, ID(n) as neo4j_id
                  RETURN COLLECT(n{.*, neo4j_id}) as info";

        $params = ['ids' => $ids];

        $result = $this->_neo4j_client->executeQuery($query, $params, true);

        $result = $result->getSingleResult();

        /** @var list<array<string, mixed>>|null $infos */
        $infos = $result?->getAsMap(0)->getAsArrayList('info')->toArray();
        $infos ??= [];
        $objects = [];

        foreach ($infos as $info) {
            /** @psalm-suppress MixedArrayOffset */
            $objects[$info[$key]] = new $model_class($info, $this);
        }

        return $objects;
    }

    /**
     * @param class-string<ModelAbstract> $model_class
     * @param array{0: string, 1: string}&array<string> $order_by
     */
    public function fetchObjectsByLabel(string $model_class, array $order_by = null, int $limit = null): array
    {
        /** @var string */
        $entity = $model_class::ENTITY;

        if ($limit === null && $order_by === null) {
            $query = "MATCH (n:$entity)
                      RETURN COLLECT(n) as info";

            $params = [];
        } elseif ($limit !== null && $order_by === null) {
            $query = "MATCH (n:$entity)
                      WITH n
                      LIMIT \$limit
                      RETURN COLLECT(n) as info";

            $params = [
                'limit' => $limit,
            ];
        } elseif ($limit === null) {
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

        $result = $this->execute($query, $params, true);

        /** @psalm-suppress TooManyTemplateParams */
        $infos = $result->getAsMap(0)
            ->getAsArrayList('info')
            ->map(static fn(CypherMap $m) => $m->toArray())
            ->toArray();

        return $model_class::fromDataList($infos, $this);
    }

    /**
     * @param class-string<ModelAbstract> $model_class
     * @param array<string, mixed> $props
     * @param array{0: string, 1: string}&array<string> $order_by
     */
    public function fetchObjectsByLabelAndProps(string $model_class, array $props, array $order_by = null, int $limit = null): array
    {
        /** @var string */
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
                      LIMIT \$limit
                      RETURN COLLECT(n) as info";

            $params = array_merge($where_props, [
                'limit' => $limit,
            ]);
        } elseif ($limit === null) {
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
                      LIMIT \$limit
                      RETURN COLLECT(n) as info";

            $params = array_merge($where_props, [
                'limit' => $limit,
            ]);
        }


        /** @psalm-suppress TooManyTemplateParams */
        return $model_class::fromDataList(
            $this->execute($query, $params, true)
                ->getAsMap(0)
                ->getAsMap('info')
                ->map(static fn(Node $x) => $x->getProperties()->toArray())
                ->toArray(),
            $this
        );
    }

    public function createNode(NodeModelAbstract $object): NodeModelAbstract
    {
        $entity = $object->getEntityType();

        $property_info = $object->getPropertyInfo();

        if (isset($property_info['extra'][ModelAbstract::TYPE_AUTO_INCREMENT])) {
            $auto_increment_id_field = $property_info['extra'][ModelAbstract::TYPE_AUTO_INCREMENT];
            $query = "
                     MERGE (n:SimpleNeo4jConfig{name:\$config_name})
                     SET n.lock = \$lock
                     WITH n
                     SET n.n = COALESCE(n.n, 0) + 1
                     REMOVE n.lock
                     WITH 
                        n.n as use_id
                     CREATE (n:$entity{{$auto_increment_id_field}:use_id})
                     SET n += \$props
                     WITH n, ID(n) as neo4j_id
                     RETURN n{.*, neo4j_id} as info
                     ";

            $params = [
                'config_name' => 'ai_' . $entity,
                'lock' => 1,
                'props' => $property_info['props'],
            ];
        } else {
            $primary_field = $property_info['extra'][ModelAbstract::PROP_INFO_PRIMARY] ?? throw new LogicException('Primary field must be defined if no auto increment has been given');

            $query = "
                     CREATE (n:$entity{{$primary_field}:\$use_id})
                     SET n += \$props
                     WITH n, ID(n) as neo4j_id
                     RETURN n{.*, neo4j_id} as info
                     ";

            $params = [
                'props' => $property_info['props'],
                'use_id' => $property_info['props'][$primary_field],
            ];
        }

        try {
            $result = $this->execute($query, $params, false);

            return $object->withProperties($result->getAsMap(0)->getAsMap('info')->toArray(), $this);
        } catch (HttpClient\Exception\CypherQueryException $e) {
            if ($e->isConstraintViolation()) {
                throw new ConstraintViolationException();
            }

            throw $e;
        }
    }

    public function createRelationship(RelationshipModelAbstract $relationship): RelationshipModelAbstract
    {
        $from_node_info = $relationship->getStartNode()->getPropertyInfo();

        $relationship_info = $relationship->getPropertyInfo();

        $to_node_info = $relationship->getEndNode()->getPropertyInfo();

        $n1_primary_field = $from_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY] ?? '';
        $n2_primary_field = $to_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY] ?? '';

        /** @var string $n1_id */
        $n1_id = $from_node_info['props'][$n1_primary_field];
        /** @var string $n2_id */
        $n2_id = $to_node_info['props'][$n2_primary_field];

        $relationship_type = $relationship->getEntityType();

        $from_type = $relationship->getStartNode()->getEntityType();
        $to_type = $relationship->getEndNode()->getEntityType();
        if (isset($relationship_info['extra']['unique']) && $relationship_info['extra']['unique'] === true) {
            $query = "
                     MATCH (n1:{$from_type}{{$n1_primary_field}:\$n1_id}), (n2:{$to_type}{{$n2_primary_field}:\$n2_id})
                     WITH n1, n2
                     MERGE (n1)-[r:{$relationship_type}]->(n2)
                     ON CREATE SET r = \$rel_props
                     WITH r, ID(r) as neo4j_id
                     RETURN r{.*, neo4j_id} as info
                  ";

            $result = $this->execute($query, [
                'n1_id' => $n1_id,
                'n2_id' => $n2_id,
                'rel_props' => $relationship_info['props'],
            ], false);
        } else {
            $query = "
                     MATCH (n1:{$from_type}{{$n1_primary_field}:\$n1_id}), (n2:{$to_type}{{$n2_primary_field}:\$n2_id})
                     WITH n1, n2
                     CREATE (n1)-[r:{$relationship_type}]->(n2)
                     SET r = \$rel_props
                     WITH r, ID(r) as neo4j_id
                     RETURN r{.*, neo4j_id} as info
                  ";

            $result = $this->execute($query, [
                'n1_id' => $n1_id,
                'n2_id' => $n2_id,
                'rel_props' => $relationship_info['props'],
            ], false);
        }

        return $relationship->withProperties($result->getAsMap(0)->getAsMap('info')->toArray());
    }

    /**
     * @param array{0: string, 1: string}&array<string> $orders
     */
    public static function getOrderParts(array $orders, string $prefix = ''): string
    {
        $orders_strings = [];

        foreach ($orders as $order_info) {
            $orders_strings[] = $prefix . $order_info[0] . ' ' . $order_info[1];
        }

        return implode(', ', $orders_strings);
    }

    /**
     * @param array<string> $relation_types
     * @return array<string, list<RelationshipModelAbstract>>
     */
    public function loadRelationsForNode(NodeModelAbstract $node, array $relation_types): array
    {
        $entity = $node->getEntityType();
        $node_id_info = $node->getPrimaryIdInfo();

        $relations_info = $node->getRelationsInfo($relation_types);

        $formatted_relations = [];

        foreach ($relations_info as $relation_name => $relation_info) {
            $formatted_relations[$relation_name] = [];
            if (($relation_info[ModelAbstract::PROP_INFO_RELATED_DIRECTION] ?? '') === ModelAbstract::PROP_INFO_RELATED_DIRECTION_OUTGOING) {
                $left_arrow = '';
                $right_arrow = '>';
            } else {
                $left_arrow = '<';
                $right_arrow = '';
            }

            if (!array_key_exists('related_type', $relation_info) || !array_key_exists('entity_type', $relation_info)) {
                throw new LogicException('No related type defined for relation');
            }

            /** @var string $relEntity */
            $relEntity = $relation_info['related_type']::ENTITY;
            /** @var string $otherEntity */
            $otherEntity = $relation_info['entity_type']::ENTITY;
            $query = '
                  MATCH (n:' . $entity . '{' . $node_id_info['name'] . ':$id})
                  WITH n
                  MATCH (n)' . $left_arrow . '-[r:' . $relEntity . ']-' . $right_arrow . '(other:' . $otherEntity . ')
                  WITH other as rel_node, r, $rel_type as rel_type, ID(r) as neo4j_id
                  RETURN rel_node, r{.*, neo4j_id} as rel_rel, rel_type
                  ';

            $params = [
                'rel_type' => $relation_name,
                'id' => $node_id_info['value'],
            ];

            $this->_neo4j_client->addQueryToBatch($query, $params, true);
        }

        $result = $this->_neo4j_client->executeBatchQueries()->getAllResults();

        foreach ($result as $relation_results) {
            /** @var CypherMap $relation_result */
            foreach ($relation_results as $relation_result) {
                /** @var array{rel_node: Node, rel_type: string, rel_rel: array{neo4j_id: int, ...<string, mixed>}} */
                $relation_result = $relation_result->toArray();
                $relation_info = $relations_info[$relation_result['rel_type']];

                /** @var class-string<RelationshipModelAbstract> $relationship_type */
                $relationship_type = $relation_info['related_type'] ?? throw new LogicException('No related type defined for relation');
                /** @var class-string<NodeModelAbstract> $other_node_type */
                $other_node_type = $relation_info['entity_type'] ?? throw new LogicException('No entity type defined for relation');
                $other_node = new $other_node_type($relation_result['rel_node'], $this);

                if (($relation_info[ModelAbstract::PROP_INFO_RELATED_DIRECTION] ?? '') === ModelAbstract::PROP_INFO_RELATED_DIRECTION_OUTGOING) {
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

    public function saveNode(NodeModelAbstract $node): ?NodeModelAbstract
    {
        $modified_properties = $node->getModifiedProperties();

        if (!$modified_properties) {
            return $node;
        }


        $primary_id_info = $node->getPrimaryIdInfo();
        $model_class = get_class($node);

        /**
         * @psalm-suppress UndefinedConstant
         * @var string $entity
         */
        $entity = $node::ENTITY;

        $key = $primary_id_info['name'];

        $query = "MATCH (n:$entity{{$key}:\$id})
                  WITH n
                  SET n += \$props
                  WITH n, ID(n) as neo4j_id
                  RETURN n{.*, neo4j_id} as info";

        $params = ['id' => $primary_id_info['value'], 'props' => $modified_properties];


        try {
            $result = $this->execute($query, $params, false);
        } catch (HttpClient\Exception\CypherQueryException $e) {
            if ($e->isConstraintViolation()) {
                throw new ConstraintViolationException();
            }

            throw $e;
        }

        return new $model_class($result->getAsMap(0)->getAsMap('info')->toArray(), $this);

    }

    public function deleteNode(NodeModelAbstract $node): void
    {
        $primary_id_info = $node->getPrimaryIdInfo();

        /**
         * @var string $entity
         * @psalm-suppress UndefinedConstant
         */
        $entity = $node::ENTITY;

        $key = $primary_id_info['name'];

        $query = "MATCH (n:$entity{{$key}:\$id})
                  WITH n
                  DETACH DELETE n";

        $params = ['id' => $primary_id_info['value']];

        $this->execute($query, $params, false);
    }

    public function saveRelationship(RelationshipModelAbstract $relationship): RelationshipModelAbstract
    {
        $modified_properties = $relationship->getModifiedProperties();

        if (!$modified_properties) {
            return $relationship;
        }

        $from_node_info = $relationship->getStartNode()->getPropertyInfo();
        $to_node_info = $relationship->getEndNode()->getPropertyInfo();

        $n1_primary_field = $from_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY] ?? '';
        $n2_primary_field = $to_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY] ?? '';

        /** @var string $n1_id */
        $n1_id = $from_node_info['props'][$n1_primary_field];
        /** @var string $n2_id */
        $n2_id = $to_node_info['props'][$n2_primary_field];

        $relationship_type = $relationship->getEntityType();

        $from_type = $relationship->getStartNode()->getEntityType();
        $to_type = $relationship->getEndNode()->getEntityType();

        $query = "
                 MATCH (n1:{$from_type}{{$n1_primary_field}:\$n1_id}), (n2:{$to_type}{{$n2_primary_field}:\$n2_id})
                 WITH n1, n2
                 MATCH (n1)-[r:{$relationship_type}]->(n2)
                 WHERE ID(r)=\$relationship_neo4j_id
                 WITH r
                 SET r += \$props
                 WITH r, ID(r) as neo4j_id
                 RETURN r{.*, neo4j_id} as info
              ";

        $result = $this->execute($query, [
            'n1_id' => $n1_id,
            'n2_id' => $n2_id,
            'relationship_neo4j_id' => $relationship->getNeo4jId(),
            'props' => $modified_properties,
        ], true);

        return $relationship->withProperties($result->getAsMap(0)->getAsMap('info')->toArray());
    }

    public function deleteRelationship(RelationshipModelAbstract $relationship): void
    {
        $from_node_info = $relationship->getStartNode()->getPropertyInfo();
        $to_node_info = $relationship->getEndNode()->getPropertyInfo();

        $n1_primary_field = $from_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY] ?? '';
        $n2_primary_field = $to_node_info['extra'][ModelAbstract::PROP_INFO_PRIMARY] ?? '';

        /** @var string $n1_id */
        $n1_id = $from_node_info['props'][$n1_primary_field];
        /** @var string $n2_id */
        $n2_id = $to_node_info['props'][$n2_primary_field];

        $relationship_type = $relationship->getEntityType();

        $from_type = $relationship->getStartNode()->getEntityType();
        $to_type = $relationship->getEndNode()->getEntityType();

        $query = "
                 MATCH (n1:{$from_type}{{$n1_primary_field}:\$n1_id}), (n2:{$to_type}{{$n2_primary_field}:\$n2_id})
                 WITH n1, n2
                 MATCH (n1)-[r:{$relationship_type}]->(n2)
                 WHERE ID(r)=\$relationship_neo4j_id
                 WITH r
                 DELETE r
              ";

        $this->execute($query, [
            'n1_id' => $n1_id,
            'n2_id' => $n2_id,
            'relationship_neo4j_id' => $relationship->getNeo4jId(),
        ], false);
    }

    /**
     * @param array<string, mixed> $props
     * @return array{where_string: string, where_props: array<string, mixed>}
     */
    private function _getPropsInfo(array $props): array
    {
        $where_parts = [];
        $where_props = [];

        /** @var mixed $prop_value */
        foreach ($props as $prop_name => $prop_value) {
            $where_parts[] = 'n.' . $prop_name . '=$n_' . $prop_name;
            /** @var mixed */
            $where_props['n_' . $prop_name] = $prop_value;
        }

        return [
            'where_string' => $where_parts ? ('WHERE ' . implode(' AND ', $where_parts)) : '',
            'where_props' => $where_props,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function execute(string $query, array $params, bool $readonly): SummarizedResult
    {
        $result = $this->_neo4j_client->executeQuery($query, $params, $readonly);

        // We have no option but to throw an exception here, as we can't return null,
        // if the client has been configured not to throw errors we can use the
        // stored error result instead.
        /**
         * @psalm-suppress InvalidThrow
         * @var SummarizedResult
         */
        return $result->getSingleResult() ?? throw $result->getFirstError();
    }
}
