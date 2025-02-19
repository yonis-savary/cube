<?php

namespace Cube\Http\Exceptions;

use RuntimeException;
use Cube\Http\Request;

class InvalidRequestException extends RuntimeException
{
    public function __construct(
        public array $errors,
        public Request $request
    )
    {
        parent::__construct("Given request is not valid");
    }
}