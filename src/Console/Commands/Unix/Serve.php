<?php

namespace Cube\Console\Commands\Unix;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Web\Servers\UnixSocketServer;

class Serve extends Command
{
    public function getScope(): string
    {
        return 'unix';
    }

    public function getHelp(): string
    {
        return 'Serve the socket routes of your app on a unix socket';
    }

    public function execute(Args $args): int
    {
        if (!$socketPath = $args->getValues()[0] ?? null)
            return $this->abort('A socket path is needed, e.g. php do unix:serve /run/my-app.sock');

        (new UnixSocketServer($socketPath))->serve();

        return 0;
    }
}
