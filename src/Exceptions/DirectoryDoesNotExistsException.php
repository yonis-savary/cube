<?php

namespace Cube\Exceptions;

use InvalidArgumentException;

class DirectoryDoesNotExistsException extends InvalidArgumentException
{
    public function __construct(string $file)
    {
        parent::__construct("Directory [$file] does not exists !");
    }
}