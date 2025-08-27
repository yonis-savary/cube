<?php 

namespace Cube\Console\Commands\Websocket;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Web\Websocket\Websocket;

class Serve extends Command
{
    public function getScope(): string
    {
        return 'websocket';
    }

    public function execute(Args $args): int
    {
        Websocket::getInstance()->serve();
        return 0;
    }
}