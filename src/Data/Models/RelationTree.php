<?php

namespace Cube\Data\Models;

class RelationTree
{
    protected array $tree = [];

    public function __construct(string ...$relations)
    {
        foreach ($relations as $relation)
            $this->addRelation($relation);
    }

    public function addRelation(string $relation) {
        $path = explode(".", $relation);

        $ref = &$this->tree;
        foreach ($path as $step)
        {
            $ref[$step] ??= [];
            $ref = &$ref[$step];
        }
    }

    public function getTree() {
        return $this->tree;
    }
}