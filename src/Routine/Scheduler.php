<?php

namespace Cube\Routine;

use DateTime;
use Cube\Core\Component;

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