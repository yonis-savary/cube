<?php

namespace YonisSavary\Cube\Models\Relations;

interface Relation
{
    public function concern(string $model): bool;

    public function isSource(string $model, string $column): bool;

    public function load(): void;

    public function getName(): string;
}