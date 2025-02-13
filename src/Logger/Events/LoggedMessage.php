<?php

namespace YonisSavary\Cube\Logger\Events;

use Stringable;
use YonisSavary\Cube\Event\AbstractEvent;

class LoggedMessage extends AbstractEvent
{
    public function __construct(
        readonly public string $level,
        readonly public string|Stringable $message,
        readonly public array $context=[]
    ) {}
}