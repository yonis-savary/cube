<?php

namespace Cube\Tests\Units\Data\OpenAPI\Middlewares;

use Cube\Data\OpenAPI\Attributes\RawResponse;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;
use Cube\Web\Middleware;

#[RawResponse(['error' => 'Missing API key'], responseCode: 401, description: 'No API key')]
#[RawResponse(['error' => 'Forbidden'], responseCode: 403, description: 'Refused by the middleware')]
class RequiresAnApiKey implements Middleware
{
    public static function handle(Request $request, \Closure $next): Request|Response
    {
        return $next($request);
    }
}
