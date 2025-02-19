<?php

namespace Cube\Logger\Events;

use Stringable;
use Cube\Event\Event;

class LoggedMessage extends Event
{
    public function __construct(
        readonly public string $level,
        readonly public string|Stringable $message,
        readonly public array $context=[]
    ) {}
}