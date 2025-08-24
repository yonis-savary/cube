<?php

namespace Cube\Web;

use Cube\Web\Http\Request;
use Cube\Web\Http\Response;

interface Middleware
{
    public static function handle(Request $request): Request|Response;
}
