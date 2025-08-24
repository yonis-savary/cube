<?php

namespace Cube\Data\Models\Relations;

use Cube\Data\Models\Model;

interface Relation
{
    public function concern(string $model): bool;

    public function isSource(string $model, string $column): bool;

    public function load(): Model|array;

    public function getName(): string;
}
