<?php

namespace Cube\Env\Logger\Events;

use Cube\Event\Event;

class LoggedMessage extends Event
{
    public function __construct(
        readonly public string $level,
        readonly public string|\Stringable $message,
        readonly public array $context = []
    ) {}
}
