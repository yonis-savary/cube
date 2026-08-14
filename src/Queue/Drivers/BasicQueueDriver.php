<?php

namespace Cube\Queue\Drivers;

abstract class BasicQueueDriver implements QueueDriver
{
    protected string $identifier;

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }
}