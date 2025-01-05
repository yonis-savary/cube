<?php

namespace YonisSavary\Cube\Tests\App\Commands;

use YonisSavary\Cube\Console\Args;
use YonisSavary\Cube\Console\Command;

class DummyCommand extends Command
{
    public function execute(Args $args): int
    {
        return 0;
    }
}