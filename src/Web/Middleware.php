<?php

namespace YonisSavary\Cube\Web;

use YonisSavary\Cube\Http\Request;
use YonisSavary\Cube\Http\Response;

interface Middleware
{
    public static function handle(Request $request): Request|Response;
}