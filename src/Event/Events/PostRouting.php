<?php

namespace Cube\Event\Events;

use Cube\Event\Event;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Router\Router;

class PostRouting extends Event {
    public function __construct(
        public readonly Router $router,
        public readonly Request $request,
        public readonly Response $response,
    )
    {
    }
}