<?php

namespace Cube\Core;

use Cube\Data\Bunch;
use RuntimeException;

class RequestContext
{
    use Component;

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


    /**
     * @template TReturn
     * @param \Closure(self):TReturn $callback
     * @param class-string<Component>[] $persistentComponents
     * @return TReturn
     */
    public static function oneShot(callable $callback, array $persistentComponents = []): mixed {
        $instance = new static($persistentComponents);
        return $instance->run($callback);
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

    protected function resetToLastSnapshot(): void
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

    protected function freshInstances(): void
    {
        foreach ($this->componentsToReset as $class) {
            $class::removeInstance();
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
        $this->freshInstances();
        try {
            return $this->asGlobalInstance($callback);
        } finally {
            $this->resetToLastSnapshot();
        }
    }
}
