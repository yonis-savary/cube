<?php

namespace Cube\Web\Http\Exceptions;

use Cube\Web\Http\Request;

class InvalidRequestException extends \RuntimeException
{
    public function __construct(
        public array $errors,
        public Request $request
    ) {
        parent::__construct('Given request is not valid');
    }
}
