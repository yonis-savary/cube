<?php

namespace Cube\Web;

use Closure;
use Cube\Web\Http\Request;
use Cube\Web\Http\Response;

interface Middleware
{
    /**
     * @param \Closure(Request):mixed
     */
    public static function handle(Request $request, Closure $next): Request|Response;
}
