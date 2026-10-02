<?php

namespace Cube\Console\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Core\Injector;
use Cube\Data\Bunch;
use Cube\Event\Event;
use Cube\Event\EventDispatcher;
use Cube\Utils\Console;
use Override;

class Dispatch extends Command {
    public function __construct(
        protected EventDispatcher $dispatcher,
        protected Injector $injector
    )
    {
    }

    #[Override]
    public function getScope(): string
    {
        return 'cube';
    }

    /**
     * @return class-string<Event>|null
     */
    protected function getClassFromEvent(string $event): ?string {
        if (!class_exists($event)) {

            $match = Bunch::of(Autoloader::classesThatExtends(Event::class))
                ->first(fn($class) => str_ends_with($class, $event));

            if (!$match) {
                Console::print("No Event class found for value [$event]");
                return null;
            }

            $event = $match;
        }

        if (!Autoloader::extends($event, Event::class)) {
            Console::print("Given class $event does not extend from Event");
            return null;
        }

        return $event;
    }

    #[Override]
    public function execute(Args $args): int
    {
        $event = $args->getValue('e', 'event');
        if (!$event)
            return $this->abort("An event classname is neeeded (-e|--event)");

        $event = $this->getClassFromEvent($event);
        if (!$event)
            return $this->abort("No valid Event name found.", 2);

        $event = $this->injector->instanciate($event);
        $this->dispatcher->dispatch($event);
        return 0;
    }
}