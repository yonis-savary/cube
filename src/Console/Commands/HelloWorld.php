<?php

namespace YonisSavary\Cube\Console\Commands;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;
use YonisSavary\Cube\Utils\Console;

class HelloWorld extends Command
{
    public function getHelp(): string
    {
        return "Simply prints \"Hello World !\" in the console";
    }

    public function getScope(): string
    {
        return "cube";
    }

    public function execute(Args $args): int
    {
        Console::log("Hello World !");
        return 0;
    }
}