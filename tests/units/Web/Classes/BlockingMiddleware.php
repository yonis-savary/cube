<?php

namespace Cube\Tests\Units\Web\Classes;

use Closure;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Http\StatusCode;
use Cube\Web\Middleware;

/**
 * Answers on its own and never calls the next stage, the way a failed authorization check does.
 */
class BlockingMiddleware implements Middleware
{
    public static function handle(Request $request, Closure $next): Request|Response
    {
        return new Response(StatusCode::FORBIDDEN, 'blocked');
    }
}
