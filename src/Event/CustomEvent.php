<?php

namespace YonisSavary\Cube\Event;

class CustomEvent extends AbstractEvent
{
    public function getName(): string
    {
        return $this->name;
    }

    public function __construct(
        public string $name,
        public mixed $data=null
    ){}
}