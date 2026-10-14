<?php

namespace Cube\Console\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Core\Autoloader;
use Cube\Core\Injector;
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

    #[Override]
    public function getHelp(): string
    {
        return 'Dispatch an event by class name or its end, e.g. php do cube:dispatch -e PostDeployment';
    }

    /**
     * @return class-string<Event>|null
     */
    protected function getClassFromEvent(string $event): ?string {
        if (!class_exists($event)) {

            $candidates = $this->classesNamed(Event::class, $event);

            if ($candidates->count() !== 1) {
                Console::print($candidates->count()
                    ? "[$event] matches several Event classes, give one of them : ".$candidates->join(', ')
                    : "No Event class found for value [$event]");
                return null;
            }

            $event = $candidates->first();
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