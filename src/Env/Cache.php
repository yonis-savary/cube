<?php

namespace YonisSavary\Cube\Env;

use YonisSavary\Cube\Core\Component;
use YonisSavary\Cube\Env\Cache\Element;
use YonisSavary\Cube\Env\Storage;

class Cache
{
    const SECOND = 1;
    const MINUTE = self::SECOND * 60;
    const HOUR   = self::MINUTE * 60;
    const DAY    = self::HOUR * 24;
    const WEEK   = self::DAY * 7;
    const MONTH  = self::DAY * 31;

    use Component;

    protected Storage $storage;
    /** @var array<string,Element> */
    protected array $index;

    public static function getDefaultInstance(): static
    {
        return new self(Storage::getInstance()->getSubStorage("Cache"));
    }

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;

        foreach ($storage->listFiles() as $file)
        {
            if (! $element = Element::fromFile($file))
                continue;

            $this->index[$element->key] = $element;
        }
    }

    public function get(string $key, mixed $default=null): mixed
    {
        return $this->index[$key] ?? $default;
    }

    public function set(string $key, mixed $value, int $timeToLive=self::MONTH, ?int $creationDate=null)
    {
        $creationDate ??= time();

        $this->delete($key);

        $element = new Element($key, $value, $timeToLive, $creationDate);
        $this->index[$key] = $element;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->index);
    }

    public function delete(string $key)
    {
        if ($this->has($key))
        {
            $this->index[$key]->destroy();
            unset($this->index[$key]);
        }
    }

    public function save()
    {
        foreach ($this->index as $element)
            $element->save($this->storage);
    }

    public function getSubCache(string $name): self
    {
        return new Cache($this->storage->getSubStorage($name));
    }

    public function __destruct()
    {
        $this->save();
    }
}