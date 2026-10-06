<?php

namespace Cube\Event\Events;

use Cube\Event\Event;
use Cube\Web\Http\Response;

class DisplayingResponse extends Event {
    public function __construct(
        public readonly Response $response
    )
    {}
}