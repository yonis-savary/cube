<?php

namespace Cube\Env\Logger;

class NullLogger extends Logger
{
    public function __construct() {}

    public static function getDefaultInstance(): static
    {
        return new static();
    }

    public function __destruct() {}

    public function log($level, string|\Stringable|null $message, array $context = []): void {}
}
