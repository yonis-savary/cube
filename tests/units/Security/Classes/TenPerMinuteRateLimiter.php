<?php

namespace Cube\Tests\Units\Security\Classes;

use Cube\Env\Cache;
use Cube\Security\RateLimiting\RateLimiter;

class TenPerMinuteRateLimiter extends RateLimiter
{
    public function __construct(
        protected Cache $cache,
        protected FakeClock $clock
    ) {}

    protected function limit(): int
    {
        return 10;
    }

    protected function window(): int
    {
        return Cache::MINUTE;
    }

    protected function cache(): Cache
    {
        return $this->cache;
    }

    protected function now(): float
    {
        return $this->clock->now;
    }

    protected function sleep(float $seconds): void
    {
        $this->clock->sleep($seconds);
    }
}
