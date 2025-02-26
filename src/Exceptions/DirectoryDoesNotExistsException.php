<?php

namespace Cube\Exceptions;

class DirectoryDoesNotExistsException extends \InvalidArgumentException
{
    public function __construct(string $file)
    {
        parent::__construct("Directory [{$file}] does not exists !");
    }
}
