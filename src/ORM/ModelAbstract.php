<?php

namespace SimpleNeo4j\ORM;

abstract class ModelAbstract {

    const PROP_INFO_KEY = 'model_info';
    const PROP_INFO_TYPE = 'type';
    const PROP_INFO_UNIQUE = 'unique';
    const PROP_INFO_DEFAULT = 'default';
    const PROP_INFO_PRIMARY = 'primary';
    const PROP_INFO_RELATED_TYPE = 'related_type';
    const PROP_INFO_ENTITY_TYPE = 'entity_type';
    const PROP_INFO_RELATED_DIRECTION = 'related_direction';
    const PROP_INFO_RELATED_DIRECTION_OUTGOING = 'outgoing';
    const PROP_INFO_OPTIONAL = 'optional';

    const TYPE_STRING = 'string';
    const TYPE_INTEGER = 'integer';
    const TYPE_AUTO_INCREMENT = 'auto_increment';
    const TYPE_CREATED_ON = 'created_on';
    const TYPE_MODIFIED_ON = 'modified_on';
    const TYPE_RELATION = 'relation';

    const NON_VALUE_TYPES = [
        self::TYPE_AUTO_INCREMENT,
        self::TYPE_CREATED_ON,
        self::TYPE_MODIFIED_ON,
    ];

    const IS_UNIQUE = false;

    protected $_field_info;

    /**
     * @var Manager
     */
    protected $_manager;

    private $_neo4j_id;
    private $_has_unsaved_changes;

    public function __construct(array $data = [], Manager $manager = null)
    {
        $this->_manager = $manager;

        $this->_field_info = [];

        $this_obj_props = get_object_vars($this);

        $time = null;

        foreach ($this_obj_props as $prop_name => $prop_value) {
            if (!is_array($prop_value) || !isset($prop_value[self::PROP_INFO_KEY]) || !is_array($prop_value[self::PROP_INFO_KEY])) {
                continue;
            }

            $prop_value = $prop_value[self::PROP_INFO_KEY];

            if (!isset($prop_value[self::PROP_INFO_TYPE])) {
                throw new Exception\MisconfiguredPropertyException('Property is misconfigured - missing "type"');
            }

            $clean_prop_name = substr($prop_name, 1);

            $use_value = null;

            if (!array_key_exists($clean_prop_name, $data)) {
                if (array_key_exists(self::PROP_INFO_DEFAULT, $prop_value)) {
                    $use_value = $prop_value[self::PROP_INFO_DEFAULT];
                } elseif (in_array($prop_value[self::PROP_INFO_TYPE], self::NON_VALUE_TYPES)) {
                    switch ($prop_value[self::PROP_INFO_TYPE]) {
                        case self::TYPE_CREATED_ON:
                        case self::TYPE_MODIFIED_ON:
                            if ($time === null) {
                                $time = time();
                            }
                            $use_value = $time;
                            break;
                        default:
                            $use_value = [
                                self::PROP_INFO_KEY => $prop_value,
                            ];
                    }
                } else {
                    throw new Exception\MissingPropertyException('Property is missing - ' . $clean_prop_name);
                }
            } else {
                $use_value = $data[$clean_prop_name];
            }

            $this->_field_info[$clean_prop_name] = $prop_value;

            if (($prop_value[self::PROP_INFO_TYPE] != self::TYPE_AUTO_INCREMENT && $prop_value[self::PROP_INFO_TYPE] != self::TYPE_RELATION) || is_numeric($use_value)) {
                $this->{$prop_name} = $use_value;
            } else {
                unset($this->{$prop_name});
            }
        }

        $this->_neo4j_id = $data['neo4j_id'] ?? null;
        $this->_has_unsaved_changes = $this->_neo4j_id === null;

    }

    public function getPropertyInfo() : array
    {
        $property_into = [
            'props' => [],
            'extra' => [
                self::PROP_INFO_UNIQUE => [],
            ],
        ];

        foreach ($this->_field_info as $field_name => $field_value) {
            if ($field_value[self::PROP_INFO_TYPE] == self::TYPE_AUTO_INCREMENT) {
                $property_into['extra'][self::TYPE_AUTO_INCREMENT] = $field_name;

                if (isset($this->{"_" . $field_name})) {
                    $property_into['props'][$field_name] = $this->{"_" . $field_name};
                }
            }
            elseif ($field_value[self::PROP_INFO_TYPE] == self::TYPE_RELATION) {

            } else {
                $property_into['props'][$field_name] = $this->{"_" . $field_name};
            }

            if (isset($field_value[self::PROP_INFO_PRIMARY]) && $field_value[self::PROP_INFO_PRIMARY] === true) {
                $property_into['extra'][self::PROP_INFO_PRIMARY] = $field_name;
            }
        }

        if (static::IS_UNIQUE) {
            $property_into['extra']['unique'] = true;
        }

        return $property_into;
    }

    public function getEntityType() : string
    {
        return static::ENTITY;
    }

    public function isUniqueEntity() : bool
    {
        return static::IS_UNIQUE;
    }

    public static function fromDataList(array $infos) : array
    {
        $objects = [];

        foreach ($infos as $info) {
            $objects[] = new static($info);
        }

        return $objects;
    }

    public function set(string $property, $value) {

    }
}
