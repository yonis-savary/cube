<?php

namespace YonisSavary\Cube\Exceptions;

use InvalidArgumentException;

class FileDoesNotExistsException extends InvalidArgumentException
{
    public function __construct(string $file)
    {
        parent::__construct("File [$file] does not exists !");
    }
}