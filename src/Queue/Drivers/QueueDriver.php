<?php 

namespace Cube\Queue\Drivers;

interface QueueDriver
{
    public function setIdentifier(string $identifier);

    public function next(): array;

    public function flush();

    public function push(array $args);
}