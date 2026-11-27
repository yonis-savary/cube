<?php

namespace Cube\Security\RateLimiting;

use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\Middleware;

/**
 * Answers 429 to a request its `RequestRateLimiter` refuses. Extend it once per policy and give the subclass to your routes.
 */
#[RawResponse('Too many requests', responseCode: StatusCode::TOO_MANY_REQUESTS, description: 'Too many requests, retry after the Retry-After header seconds', mimeType: 'text/plain')]
abstract class Throttle implements Middleware
{
    abstract protected static function limiter(): RequestRateLimiter;

    final public static function handle(Request $request, \Closure $next): Response
    {
        $rateLimit = static::limiter()->attemptRequest($request);
        if (!$rateLimit->allowed)
            return Response::tooManyRequests('Too many requests')->withHeaders($rateLimit->toHeaders());
        return $next($request)->withHeaders($rateLimit->toHeaders());
    }
}
