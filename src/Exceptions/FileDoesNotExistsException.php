<?php

namespace Cube\Exceptions;

use InvalidArgumentException;

class FileDoesNotExistsException extends InvalidArgumentException
{
    public function __construct(string $file)
    {
        parent::__construct("File [$file] does not exists !");
    }
}