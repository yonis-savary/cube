<?php

namespace Cube\Core;

use Cube\Data\Bunch;

class RequestContext
{
    /** @var array<class-string<Component>> */
    protected array $componentsToReset;

    /** @var array<class-string<Component>,Component>[] */
    protected array $instancesStack = [];

    /**
     * @param class-string<Component>[] $persistentComponents
     */
    public function __construct(array $persistentComponents = [])
    {
        $this->componentsToReset = Bunch::of(Autoloader::classesThatUses(Component::class))
            ->diff($persistentComponents)
            ->get();
    }


    protected function snapshotInstances(): void
    {
        $instances = [];

        foreach ($this->componentsToReset as $class) {
            if ($class::hasInstance())
                $instances[$class] = $class::getInstance();

            $class::removeInstance();
        }

        $this->instancesStack[] = $instances;
    }

    protected function resetComponents(): void
    {
        $lastInstances = count($this->instancesStack)
            ? array_pop($this->instancesStack)
            : [];

        foreach ($this->componentsToReset as $class) {
            array_key_exists($class, $lastInstances)
                ? $class::setInstance($lastInstances[$class])
                : $class::removeInstance();
        }
    }

    /**
     * @template TReturn
     * @param callable():TReturn $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed
    {
        $this->snapshotInstances();
        try {
            return $callback();
        } finally {
            $this->resetComponents();
        }
    }
}
