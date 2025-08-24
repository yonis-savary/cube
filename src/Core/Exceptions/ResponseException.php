<?php

namespace Cube\Core\Exceptions;

use Cube\Web\Http\Response;

class ResponseException extends \Exception
{
    public function __construct(
        string $message,
        public Response $response
    ) {
        $this->message = $message;
    }
}
