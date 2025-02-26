<?php

namespace Cube\Exceptions;

class FileDoesNotExistsException extends \InvalidArgumentException
{
    public function __construct(string $file)
    {
        parent::__construct("File [{$file}] does not exists !");
    }
}
