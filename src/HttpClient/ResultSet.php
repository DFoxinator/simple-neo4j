<?php

namespace SimpleNeo4j\HttpClient;

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\SummaryCounters;
use SimpleNeo4j\HttpClient\Exception\CypherQueryException;

class ResultSet
{
    /**
     * @param list<SummarizedResult|CypherQueryException> $_results
     */
    public function __construct(private array $_results = [])
    {
    }

    public function hasError(): bool
    {
        return count($this->getAllErrors()) > 0;
    }

    public function getSingleResult(): ?SummarizedResult
    {
        return $this->getAllResults()[0] ?? null;
    }

    /**
     * @return list<SummarizedResult>
     */
    public function getAllResults(): array
    {
        return array_values(
            array_filter($this->_results, static fn($x) => $x instanceof SummarizedResult)
        );
    }

    public function getFirstError(): ?Exception\CypherQueryException
    {
        return ($this->_errors[0] ?? null);
    }

    public function getAllStats(): SummaryCounters
    {
        $counters = new SummaryCounters();
        foreach ($this->getAllResults() as $result) {
            $counters = $counters->merge($result->getSummary()->getCounters());
        }

        return $counters;
    }

    /**
     * @return list<CypherQueryException>
     */
    public function getAllErrors(): array
    {
        return array_values(
            array_filter($this->_results, static fn($x) => $x instanceof CypherQueryException)
        );
    }

    public function getNumNodesCreated(): int
    {

        return $this->getAllStats()->nodesCreated();
    }

    public function getNumNodesDeleted(): int
    {
        return $this->getAllStats()->nodesDeleted();
    }

    public function getNumRelationshipsCreated(): int
    {
        return $this->getAllStats()->relationshipsCreated();
    }

    public function getNumRelationshipsDeleted(): int
    {
        return $this->getAllStats()->relationshipsDeleted();
    }
}
