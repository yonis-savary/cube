<?php

namespace YonisSavary\Cube\Env\Cache;

use YonisSavary\Cube\Env\Storage;
use YonisSavary\Cube\Logger\Logger;

class Element
{
    public static function fromFile(string $file): ?Element
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);

        if (!preg_match("/^\d+_\d+_.+$/", $filename))
            return null;

        list($creationDate, $timeToLive, $key) = explode("_", $file, 3);

        $creationDate = (int) $creationDate;
        $timeToLive = (int) $timeToLive;

        $now = time();
        $expireDate = $creationDate + $timeToLive;

        if ($now < $expireDate)
        {
            unlink($file);
            return null;
        }

        $value = unserialize(file_get_contents($file));

        return new self($key, $value, $timeToLive, $creationDate, $file);
    }

    public function __construct(
        public readonly string $key,
        protected mixed $value,
        protected int $timeToLive,
        protected ?int $creationDate=null,
        protected ?string $file=null
    )
    {}

    public function setValue(mixed $value)
    {
        $this->value = $value;
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
        if ($this->file)
            unlink($this->file);
    }

    public function save(Storage $directory)
    {
        $this->destroy();

        $newName = $this->creationDate . "_" . $this->timeToLive . "_" . $this->key;

        if ($directory->write($newName, serialize($this->value)))
            $this->file = $directory->path($newName);
        else
            Logger::getInstance()->error("Could not write file [$newName] in directory [". $directory->getRoot() ."]");
    }
}