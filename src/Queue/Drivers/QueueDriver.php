<?php

namespace Cube\Queue\Drivers;

interface QueueDriver
{
    public function setIdentifier(string $identifier): void;

    /**
     * Take the next item out of the queue. (non-blocking)
     * @return ?array Arguments of the next item, `null` when nothing could be taken
     */
    public function next(): ?array;

    public function flush(): void;

    public function push(array $args): void;
}
