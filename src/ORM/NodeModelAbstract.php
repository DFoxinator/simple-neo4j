<?php

namespace SimpleNeo4j\ORM;

use LogicException;

/**
 * @psalm-import-type FieldInfo from ModelAbstract
 */
abstract class NodeModelAbstract extends ModelAbstract {

    public function withProperties(array $properties, Manager|null $manager = null) : static
    {
        return new $this($properties, $manager ?? $this->manager);
    }

    public function __get(string $name)
    {
        $relations = $this->getManagerGuarded()->loadRelationsForNode($this, [$name]);

        foreach ($relations as $relation_name => $relation_set) {
            $this->{$relation_name} = $relation_set;
        }

        /** @psalm-suppress PossiblyUndefinedVariable */
        return $this->{$relation_name};
    }

    /**
     * @param array<string> $relation_names
     * @return array<string, FieldInfo>
     */
    public function getRelationsInfo(array $relation_names) : array
    {
        $relations_info = [];

        foreach ($relation_names as $relation_name) {
            $relation_name_formatted = $relation_name;

            if ($relation_name[0] == '_') {
                $relation_name_formatted = substr($relation_name, 1);
            }

            if (isset($this->_field_info[$relation_name_formatted])) {
                $relations_info[$relation_name] = $this->_field_info[$relation_name_formatted];
            }
        }

        /** @var array<string, FieldInfo> */
        return $relations_info;
    }

    /**
     * @return array{name: string, value: mixed}
     */
    public function getPrimaryIdInfo() : array
    {
        foreach ($this->_field_info as $field_name => $field_info) {
            if (isset($field_info['primary']) && $field_info['primary'] === true) {
                return [
                    'name' => $field_name,
                    'value' => $field_info['value'],
                ];
            }
        }

        throw new LogicException('Cannot find primary ID for node');
    }

    /**
     * @return Manager
     */
    protected function getManagerGuarded(): Manager
    {
        if ($this->manager === null) {
            throw new LogicException('Cannot load relations without a manager');
        }

        return $this->manager;
    }

    protected function _preloadAllRelations(): void
    {
        $relation_names = [];

        foreach ($this->_field_info as $field_name => $field_info) {
            $field_name_formatted = '_' . $field_name;

            if ($field_info[ModelAbstract::PROP_INFO_TYPE] == ModelAbstract::TYPE_RELATION && !isset($this->{$field_name_formatted})) {
                $relation_names[] = $field_name_formatted;
            }
        }

        $relations = $this->getManagerGuarded()->loadRelationsForNode($this, $relation_names);

        foreach ($relations as $relation_name => $relation_set) {
            $this->{$relation_name} = $relation_set;
        }
    }

}
