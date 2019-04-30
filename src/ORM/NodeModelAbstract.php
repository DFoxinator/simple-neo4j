<?php

namespace SimpleNeo4j\ORM;

abstract class NodeModelAbstract extends ModelAbstract {

    public function withProperties(array $properties, Manager $manager = null) : ModelAbstract
    {
        return new $this($properties, $manager ?? $this->_manager);
    }

    public function __get($name)
    {
        $relations = $this->_manager->loadRelationsForNode($this, [$name]);

        foreach ($relations as $relation_name => $relation_set) {
            $this->{$relation_name} = $relation_set;
        }

        return $this->{$relation_name};
    }

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

        return $relations_info;
    }

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

        // throw error!
    }

}
