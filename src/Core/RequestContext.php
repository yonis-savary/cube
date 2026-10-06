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
            return $this->asGlobalInstance($callback);
        } finally {
            $this->resetComponents();
        }
    }

    /**
     * @template TComponent of Component
     * @param class-string<TComponent> $component
     * @return TComponent
     */
    public function get(string $component): mixed {
        $count = count($this->instancesStack);
        if (!$count)
            throw new RuntimeException('get() can only be called while running a context callback, see run()');

        $activeStack = &$this->instancesStack[$count-1];

        return $activeStack[$component] ??= $component::getInstance();
    }
}
