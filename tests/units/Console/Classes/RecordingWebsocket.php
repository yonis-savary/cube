<?php

namespace Cube\Tests\Units\Console\Classes;

use Cube\Env\Logger\Logger;
use Cube\Web\Websocket\Servers\Websocket;

class RecordingWebsocket extends Websocket
{
    public ?Logger $loggerWhileServing = null;

    public function serve()
    {
        $this->loggerWhileServing = Logger::getInstance();
    }
}
