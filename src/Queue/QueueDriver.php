<?php 

namespace Cube\Queue;

use Cube\Queue\QueueCallback;

interface QueueDriver
{
    /**
     * return null if no item found
     */
    public function next(callable $function);

    public function flush();

    public function push(callable $function, mixed $args);
}