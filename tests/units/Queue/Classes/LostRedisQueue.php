<?php

namespace Cube\Tests\Units\Queue\Classes;

use Cube\Queue\Drivers\RedisQueue;

class LostRedisQueue extends RedisQueue
{
    public function loseTheServer(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->host = '127.0.0.1';
        $this->port = (int) explode(':', stream_socket_get_name($server, false))[1];
        $this->connection->connect($this->host, $this->port);
        fclose($server);
    }
}
