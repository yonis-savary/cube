<?php

namespace Cube\Tests\Units\Core\Classes;

use Psr\Log\LoggerInterface;

class Logbook
{
    public function __construct(
        public LoggerInterface $logger
    ) {}
}
