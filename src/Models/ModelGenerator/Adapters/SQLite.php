<?php

namespace YonisSavary\Cube\Models\ModelGenerator\Adapters;

class SQLite extends AbstractDatabaseAdapter
{
    public function getSupportedDriver(): string|array
    {
        return "sqlite";
    }

    public function process(): void
    {

    }
}