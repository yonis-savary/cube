<?php

namespace Cube\Web\Websocket\Channel;

use Ratchet\ConnectionInterface;

class ChannelSubscriber
{
    public function __construct(
        public string $path,
        public ConnectionInterface $connection
    )
    {}
}