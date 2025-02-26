<?php

namespace Cube\Event;

class CustomEvent extends Event
{
    public function __construct(
        public string $name,
        public mixed $data = null
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}
