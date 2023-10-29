<?php

namespace SimpleNeo4j\HttpClient\Exception;

use Throwable;

class CypherQueryException extends SimpleNeo4jException
{
    public function __construct(private string $cypherErrorCode, string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getCypherErrorCode() : string
    {
        return $this->cypherErrorCode;
    }

    public function isConstraintViolation() : bool
    {
        return $this->cypherErrorCode === 'Neo.ClientError.Schema.ConstraintValidationFailed';
    }
}
