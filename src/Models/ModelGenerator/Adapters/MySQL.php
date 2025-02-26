<?php

namespace Cube\Models\ModelGenerator\Adapters;

class MySQL extends DatabaseAdapter
{
    public function getSupportedDriver(): array|string
    {
        return 'mysql';
    }

    public function process(): void {}
}
