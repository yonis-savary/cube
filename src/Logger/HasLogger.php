<?php

namespace Cube\Logger;

trait HasLogger
{
    protected ?Logger $logger = null;

    public function getLogger(): Logger
    {
        if ($this->logger) {
            return $this->logger;
        }

        $classname = get_called_class();
        $classname = preg_replace('/.+\\\/', '', $classname);
        $classname = strtolower($classname);

        return $this->logger = new Logger($classname.'.csv');
    }

    public function logThrowable(\Throwable $thrown): void
    {
        $this->getLogger()->logThrowable($thrown);
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->emergency($message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->alert($message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->critical($message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->error($message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->warning($message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->notice($message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->info($message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->getLogger()->debug($message, $context);
    }
}
