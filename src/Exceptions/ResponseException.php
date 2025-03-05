<?php

namespace Cube\Exceptions;

use Cube\Http\Response;
use Exception;

class ResponseException extends Exception
{
    public function __construct(
        string $message,
        public Response $response
    )
    {
        $this->message = $message;
    }
}