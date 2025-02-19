<?php

namespace Cube\Console\Commands;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Utils\Console;

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