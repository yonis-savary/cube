<?php

namespace Cube\Logger;

class NullLogger extends Logger
{
    public function __construct() {}

    public function __destruct() {}

    public function log($level, null|string|\Stringable $message, array $context = []): void {}
}
