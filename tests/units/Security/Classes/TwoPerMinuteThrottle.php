<?php

namespace Cube\Tests\Units\Security\Classes;

use Cube\Security\RateLimiting\RequestRateLimiter;
use Cube\Security\RateLimiting\Throttle;

class TwoPerMinuteThrottle extends Throttle
{
    protected static function limiter(): RequestRateLimiter
    {
        return new TwoPerMinuteRequestRateLimiter();
    }
}
