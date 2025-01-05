<?php

namespace YonisSavary\Cube\Database\Builders;

use YonisSavary\Cube\Database\Database;
use YonisSavary\Cube\Database\Query;

interface BuilderInterface
{
    /** @return string|array<string> */
    public function getSupportedPDODriver(): string|array;

    public function build(Query $query, Database $database): string;
}