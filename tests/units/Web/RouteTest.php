<?php

namespace Cube\Tests\Units\Web;

use Cube\Web\Router\Route;
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    public function testBuildPath() {
        $route = Route::get("/product/{product}/price/{price}", fn() => null);

        $this->assertEquals("/product/1/price/2", $route->buildPath([1,2]));
    }
}