<?php

namespace Cube\Web\Websocket\Channel;

use Cube\Web\Router\Route;

interface ChannelInterface
{
    public function getRoute(): string;
}