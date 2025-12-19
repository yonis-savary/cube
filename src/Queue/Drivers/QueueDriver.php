<?php 

namespace Cube\Queue\Drivers;

use Cube\Queue\QueueCallback;

interface QueueDriver
{
    /**
     * return null if no item found
     */
    public function next(): QueueCallback;

    public function flush();

    public function push(QueueCallback $callback);
}