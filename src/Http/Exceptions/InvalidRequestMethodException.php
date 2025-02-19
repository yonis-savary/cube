<?php

namespace Cube\Http\Exceptions;

use RuntimeException;

class InvalidRequestMethodException extends RuntimeException
{
    public function __construct(
        public readonly string $requestMethod,
        public readonly array $allowedMethods
    )
    {
        parent::__construct("Forbidden method, got $requestMethod method, only " . join(", ", $allowedMethods) . " are allowed");
    }
}