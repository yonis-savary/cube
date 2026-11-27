<?php

namespace Cube\Security\RateLimiting;

use Cube\Web\Http\Request;

/**
 * A rate limiting policy whose key comes from the request : the client address by default.
 */
abstract class RequestRateLimiter extends RateLimiter
{
    public function attemptRequest(Request $request, int $cost = 1): RateLimit
    {
        return $this->attempt($this->keyOf($request), $cost);
    }

    protected function keyOf(Request $request): string
    {
        // A request without address (unix socket server) shares one counter with every other
        return $request->getClientIp() ?? 'no-address';
    }
}
