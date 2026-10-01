<?php

namespace Cube\Tests\Units\Web\Classes;

use Cube\Web\Http\Request;
use Cube\Web\Http\Response;

class ProductReader
{
    public static function read(Request $request, int $id): Response
    {
        return Response::ok("product {$id}");
    }
}
