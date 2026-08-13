<?php

namespace Cube\Tests\Units\Env\Classes;

use Cube\Env\Logger\NullLogger;

class SpyLogger extends NullLogger
{
    /** @var array<int,array{string,string}> */
    public array $records = [];

    public function log($level, null|string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message];
    }

    /** @return string[] */
    public function levels(): array
    {
        return array_column($this->records, 0);
    }
}
