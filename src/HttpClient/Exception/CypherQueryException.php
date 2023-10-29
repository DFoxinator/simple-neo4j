<?php

namespace SimpleNeo4j\HttpClient\Exception;

class CypherQueryException extends SimpleNeo4jException
{
    private string|null $_cypher_error_code;

    public function setCypherErrorCode(string|null $error_code): void
    {
        $this->_cypher_error_code = $error_code;
    }

    public function getCypherErrorCode() : string
    {
        return $this->_cypher_error_code;
    }

    public function isConstraintViolation() : bool
    {
        return $this->_cypher_error_code === 'Neo.ClientError.Schema.ConstraintValidationFailed';
    }
}
