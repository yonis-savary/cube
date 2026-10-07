<?php

namespace Cube\Env\Cache\LocalDiskCache;

use Cube\Env\Cache\CacheDriverInterface;
use Cube\Env\Storage;
use Cube\Env\Logger\Logger;

class LocalDiskCacheElement
{
    protected ?string $contentHash = null;

    public function __construct(
        public readonly string $key,
        protected mixed $value,
        protected int $timeToLive,
        protected ?int $creationDate = null,
        protected ?string $file = null,
        protected bool $loaded = true
    ) {
    }

    public static function fromFile(string $file): ?self
    {
        $filename = pathinfo($file, PATHINFO_BASENAME);

        if (!preg_match('/^\d+_\d+_.+$/', $filename)) {
            return null;
        }

        list($creationDate, $timeToLive, $key) = explode('_', $filename, 3);

        $element = new self(rawurldecode($key), null, (int) $timeToLive, (int) $creationDate, $file, false);
        if ($element->isExpired()) {
            unlink($file);

            return null;
        }

        return $element;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function load(): bool
    {
        $content = @file_get_contents($this->file);
        if (false === $content) {
            $this->file = null;

            return false;
        }

        $this->value = unserialize($content);
        $this->contentHash = md5($content);
        $this->loaded = true;

        return true;
    }

    public function isExpired(): bool
    {
        return CacheDriverInterface::PERMANENT !== $this->timeToLive
            && ($this->creationDate ?? time()) + $this->timeToLive < time();
    }

    public function setValue(mixed $value)
    {
        $this->value = $value;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function &asReference(): mixed
    {
        return $this->value;
    }

    public function setTimeToLive(int $timeToLive)
    {
        $this->timeToLive = $timeToLive;
    }

    public function setCreationDate(int $creationDate)
    {
        $this->creationDate = $creationDate;
    }

    public function setFile(string $file)
    {
        $this->file = $file;
    }

    public function destroy()
    {
        if ($this->file) {
            unlink($this->file);
        }
    }

    public function save(Storage $directory)
    {
        if (!$this->loaded) {
            return;
        }

        $newSerialized = serialize($this->value);
        $newMD5 = md5($newSerialized);
        $newName = $this->creationDate.'_'.$this->timeToLive.'_'.rawurlencode($this->key);

        $sameFile = $this->file && (basename($this->file) === $newName);
        $sameContent = $this->contentHash === $newMD5;

        if ($sameFile && $sameContent) {
            return; // No need to rewrite
        }

        $this->destroy();

        if (!$directory->write($newName, $newSerialized)) {
            Logger::getInstance()->error("Could not write file [{$newName}] in directory [".$directory->getRoot().']');
            return;
        }

        $this->file = $directory->path($newName);
        $this->contentHash = $newMD5;
    }
}
