<?php

namespace Cube\Env\Cache;

use Cube\Env\Cache;
use Cube\Env\Storage;
use Cube\Logger\Logger;

class Element
{
    protected ?string $contentHash = null;

    public function __construct(
        public readonly string $key,
        protected mixed $value,
        protected int $timeToLive,
        protected ?int $creationDate = null,
        protected ?string $file = null
    ) {
        if ($file) {
            $this->contentHash = md5_file($file);
        }
    }

    public static function fromFile(string $file): ?Element
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);

        if (!preg_match('/^\\d+_\\d+_.+$/', $filename)) {
            return null;
        }

        list($creationDate, $timeToLive, $key) = explode('_', $filename, 3);

        $creationDate = (int) $creationDate;
        $timeToLive = (int) $timeToLive;

        $now = time();
        $expireDate = $creationDate + $timeToLive;

        if ((Cache::PERMANENT != $timeToLive) && ($expireDate < $now)) {
            unlink($file);

            return null;
        }

        $value = unserialize(file_get_contents($file));

        return new self($key, $value, $timeToLive, $creationDate, $file);
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
        $newSerialized = serialize($this->value);
        $newMD5 = md5($newSerialized);
        $oldMD5 = $this->contentHash;
        $hashIsDifferent = (!$oldMD5) || ($oldMD5 != $newMD5);

        $oldName = $this->file;
        $newName = $this->creationDate.'_'.$this->timeToLive.'_'.$this->key;
        $filenameIsDifferent = (!$oldName) || ($oldName != $newName);

        $needRewrite = $hashIsDifferent || $filenameIsDifferent;
        if (!$needRewrite) {
            return;
        }

        $this->destroy();

        if ($directory->write($newName, $newSerialized)) {
            $this->file = $directory->path($newName);
        } else {
            Logger::getInstance()->error("Could not write file [{$newName}] in directory [".$directory->getRoot().']');
        }
    }
}
