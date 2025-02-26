<?php

namespace Cube\Console;

abstract class Command
{
    public static function call(?Args $args = null): int
    {
        $args ??= new Args();

        /** @var self $command */
        $command = new (get_called_class());

        return $command->execute($args);
    }

    public function getHelp(): string
    {
        return 'Please write a help section for this command';
    }

    final public function getFullIdentifier(): string
    {
        return $this->getScope().':'.$this->getName();
    }

    public function getName(): string
    {
        $class = preg_replace('/.+\\\\/', '', get_called_class());
        $class = preg_replace_callback('/([a-z])([A-Z])/', fn ($m) => $m[1].'-'.$m[2], $class);

        return strtolower($class);
    }

    public function getScope(): string
    {
        $class = preg_replace('/\\\\.+/', '', get_called_class());
        $class = preg_replace_callback('/([a-z])([A-Z])/', fn ($m) => $m[1].'-'.$m[2], $class);

        return strtolower($class);
    }

    abstract public function execute(Args $args): int;
}
