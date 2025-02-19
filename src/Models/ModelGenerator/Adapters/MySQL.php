<?php

namespace Cube\Models\ModelGenerator\Adapters;

class MySQL extends DatabaseAdapter
{
    public function getSupportedDriver(): string|array
    {
        return "mysql";
    }

    public function process(): void
    {

    }
}