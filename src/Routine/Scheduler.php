<?php

namespace YonisSavary\Cube\Routine;

use DateTime;
use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Logger\Logger;
use YonisSavary\Cube\Logger\NullLogger;

class Scheduler
{
    use Component;

    protected array $handlers = [];

    public function add(CronExpression $expression, callable $callback): self
    {
        $this->handlers[] = [$expression, $callback];
        return $this;
    }

    public function launch(DateTime|string $datetime='now'): void
    {
        foreach ($this->handlers as $handler)
        {
            /** @var CronExpression $expression */
            list($expression, $callback) = $handler;

            if (!$expression->matches($datetime))
                continue;

            $callback();
        }
    }
}