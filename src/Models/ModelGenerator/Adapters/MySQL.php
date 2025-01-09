<?php

namespace YonisSavary\Cube\Models\ModelGenerator\Adapters;

class MySQL extends AbstractDatabaseAdapter
{
    public function getSupportedDriver(): string|array
    {
        return "mysql";
    }

    public function process(): void
    {

    }
}