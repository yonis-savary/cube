<?php

namespace Cube\Console\Commands\Make;

use Cube\Console\Args;
use Cube\Console\Command;
use Cube\Data\OpenAPI\OpenAPIGenerator;
use Cube\Utils\Console;
use Cube\Utils\Path;
use Cube\Web\Router\Router;

class Openapi extends Command
{
    public function getScope(): string
    {
        return "make";
    }

    public function execute(Args $args): int
    {
        $generator = OpenAPIGenerator::getInstance();
        $file = $generator->generate();

        Console::print("\nmake:openapi - Generated output file : " . Path::toRelative($file));

        return 0;
    }

}