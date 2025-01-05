<?php

namespace YonisSavary\Cube\Tests\App\Commands;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;

class Help extends Command
{
    public function getScope(): string
    {
        return "test-app";
    }

    public function getHelp(): string
    {
        return "I am in the tests directory !";
    }

    public function execute(Args $args): int
    {
        return 0;
    }
}