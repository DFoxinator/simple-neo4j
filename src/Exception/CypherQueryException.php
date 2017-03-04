<?php

namespace SimpleNeo4j\Exception;

class CypherQueryException extends SimpleNeo4jException
{
    /**
     * @var mixed
     */
    private $_cypher_error_code;

    /**
     * @param mixed $error_code
     */
    public function setCypherErrorCode($error_code)
    {
        $this->_cypher_error_code = $error_code;
    }
}
