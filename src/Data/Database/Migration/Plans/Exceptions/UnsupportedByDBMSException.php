<?php

namespace Cube\Data\Database\Migration\Plans\Exceptions;

use Exception;
use Throwable;

class UnsupportedByDBMSException extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable|null $previous = null)
    {
        return parent::__construct($message, $code, $previous);
    }
}