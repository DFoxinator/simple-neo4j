<?php

namespace SimpleNeo4j\HttpClient;

class ResultSet
{
    /**
     * @var array
     */
    private $_results = [];

    /**
     * @var array
     */
    private $_stats = [];

    /**
     * @var \SimpleNeo4j\HttpClient\Exception\CypherQueryException[]
     */
    private $_errors = [];

    public function __construct(array $data = [])
    {
        if ($data) {
            $this->_parseData($data);
        }
    }

    private function _parseData(array $data)
    {
        if (!isset($data['results']) || !is_array($data['results'])) {
            return;
        }

        foreach ($data['results'] as $n => $result_info) {
            $use_result = [];
            foreach ($result_info['data'] as $row_data) {
                $this_row = [];
                foreach ($row_data['row'] as $field_id => $val) {
                    $this_row[$result_info['columns'][$field_id]] = $val;
                }

                $use_result[] = $this_row;
            }

            if (isset($result_info['stats'])) {
                $this->_stats[$n] = $result_info['stats'];
            }

            $this->_results[] = $use_result;
        }

        if (isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $error_info) {
                $error = new Exception\CypherQueryException($error_info['message'] ?? 'Unknown Cypher error.');
                $error->setCypherErrorCode($error_info['code'] ?? 'unknown');
                $this->_errors[] = $error;
            }
        }
    }

    public function hasError() : bool
    {
        return !empty($this->_errors);
    }

    public function getSingleResult() : ?array
    {
        return ($this->_results[0] ?? null);
    }

    public function getAllResults() : array
    {
        return $this->_results;
    }

    public function getFirstError() : ?Exception\CypherQueryException
    {
        return ($this->_errors[0] ?? null);
    }

    public function getAllStats() : array {

        return $this->_stats;

    }

    public function getAllErrors() : array
    {
        return $this->_errors;
    }

    public function getStatFieldSum(string $field) : int {

        $stats = $this->getAllStats();
        $stats = array_column($stats, $field);

        return array_sum($stats);

    }

    public function getNumNodesCreated() : int {

        return $this->getStatFieldSum('nodes_created');

    }

    public function getNumNodesDeleted() : int {

        return $this->getStatFieldSum('nodes_deleted');

    }

    public function getNumRelationshipsCreated() : int {

        return $this->getStatFieldSum('relationships_created');

    }

    public function getNumRelationshipsDeleted() : int {

        return $this->getStatFieldSum('relationship_deleted');

    }
}
