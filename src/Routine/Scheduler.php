<?php

namespace Cube\Routine;

use Cube\Core\Component;
use Cube\Data\Bunch;

class Scheduler
{
    use Component;

    protected array $handlers = [];

    public function add(CronExpression $expression, callable $callback): self
    {
        $this->handlers[] = [$expression, $callback];

        return $this;
    }

    public function launch(\DateTime|string $datetime = 'now'): void
    {
        if (is_string($datetime)) {
            $datetime = new \DateTime($datetime);
        }

        $handlersToLaunch = Bunch::of($this->handlers)
            ->filter(fn ($handler) => $handler[0]->matches($datetime))
            ->map(fn ($handler) => $handler[1])
            ->toArray()
        ;

        foreach ($handlersToLaunch as $callback) {
            $callback();
        }
    }
}
