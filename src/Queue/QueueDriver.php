<?php 

namespace Cube\Queue;

interface QueueDriver
{
    /**
     * return null if no item found
     */
    public function next(): QueueCallback;

    public function flush();

    public function push(QueueCallback $callback);
}