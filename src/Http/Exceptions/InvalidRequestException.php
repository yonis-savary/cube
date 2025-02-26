<?php

namespace Cube\Http\Exceptions;

use Cube\Http\Request;

class InvalidRequestException extends \RuntimeException
{
    public function __construct(
        public array $errors,
        public Request $request
    ) {
        parent::__construct('Given request is not valid');
    }
}
