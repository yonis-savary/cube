<?php

namespace Cube\Web;

use Cube\Http\Request;
use Cube\Http\Response;

interface Middleware
{
    public static function handle(Request $request): Request|Response;
}