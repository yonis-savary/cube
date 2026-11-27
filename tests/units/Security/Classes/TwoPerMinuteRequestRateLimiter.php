<?php

namespace Cube\Tests\Units\Security\Classes;

use Cube\Env\Cache;
use Cube\Security\RateLimiting\RequestRateLimiter;

class TwoPerMinuteRequestRateLimiter extends RequestRateLimiter
{
    protected function limit(): int
    {
        return 2;
    }

    protected function window(): int
    {
        return Cache::MINUTE;
    }
}
