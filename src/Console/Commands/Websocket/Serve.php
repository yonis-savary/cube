<?php 

namespace Cube\Console\Commands\Websocket;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Env\Logger\NullLogger;
use Cube\Env\Logger\StdOutLogger;
use Cube\Web\Websocket\Servers\Websocket;

class Serve extends Command
{
    public function getScope(): string
    {
        return 'websocket';
    }

    public function getHelp(): string
    {
        return "Start Websocket server connected to your application";
    }

    public function execute(Args $args): int
    {
        $logger = $args->has("-l", "--log")
            ? new StdOutLogger()
            : new NullLogger()
        ;
        $logger->asGlobalInstance(fn() => Websocket::getInstance()->serve());
        return 0;
    }
}