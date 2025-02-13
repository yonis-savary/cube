<?php

namespace YonisSavary\Cube\Event;

abstract class AbstractEvent
{
    public function getName(): string
    {
        return get_called_class();
    }
}