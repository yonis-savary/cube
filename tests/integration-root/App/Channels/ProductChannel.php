<?php

namespace App\Channels;

use Cube\Web\Router\Route;
use Cube\Web\Websocket\Channel\Channel;

class ProductChannel extends Channel
{
    public function getRoute(): string
    {
        return "/product/{id}";
    }
}
