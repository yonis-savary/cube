<?php

namespace Cube\Database\Builders;

use Cube\Database\Database;
use Cube\Database\Query;

interface BuilderInterface
{
    /** @return string|string[] */
    public function getSupportedPDODriver(): string|array;

    public function build(Query $query, Database $database): string;
}