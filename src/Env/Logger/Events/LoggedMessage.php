<?php

namespace Cube\Env\Logger\Events;

use Cube\Event\Event;

class LoggedMessage extends Event
{
    public function __construct(
        public readonly string $level,
        public readonly string|\Stringable $message,
        public readonly array $context = []
    ) {}
}
