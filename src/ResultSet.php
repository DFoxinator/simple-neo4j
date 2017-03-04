<?php

namespace SimpleNeo4j;

use SimpleNeo4j\Exception\CypherQueryException;

class ResultSet
{
    /**
     * @var array
     */
    private $_results = [];

    /**
     * @var \SimpleNeo4j\Exception\CypherQueryException[]
     */
    private $_errors = [];

    /**
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        if ($data) {
            $this->_parseData($data);
        }
    }

    /**
     * @param $data
     */
    private function _parseData(array $data)
    {
        if (!isset($data['results']) || !is_array($data['results'])) {
            return;
        }

        foreach ($data['results'] as $result_info) {
            $use_result = [];
            foreach ($result_info['data'] as $row_data) {
                $this_row = [];
                foreach ($row_data['row'] as $field_id => $val) {
                    $this_row[$result_info['columns'][$field_id]] = $val;
                }

                $use_result[] = $this_row;
            }

            $this->_results[] = $use_result;
        }

        if (isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $error_info) {
                $error = new CypherQueryException($error_info['message'] ?? 'Unknown Cypher error.');
                $error->setCypherErrorCode($error_info['code'] ?? 'unknown');
                $this->_errors[] = $error;
            }
        }
    }

    /**
     * @return bool
     */
    public function hasError() : bool
    {
        return !empty($this->_errors);
    }

    /**
     * @return array|null
     */
    public function getSingleResult()
    {
        return ($this->_results[0] ?? null);
    }

    /**
     * @return array
     */
    public function getAllResults() : array
    {
        return $this->_results;
    }

    /**
     * @return \SimpleNeo4j\Exception\CypherQueryException|null
     */
    public function getFirstError()
    {
        return ($this->_errors[0] ?? null);
    }

    /**
     * @return \SimpleNeo4j\Exception\CypherQueryException[]
     */
    public function getAllErrors() : array
    {
        return $this->_errors;
    }
}
