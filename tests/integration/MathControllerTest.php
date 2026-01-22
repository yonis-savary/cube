<?php

namespace Cube\Tests\Integration;

use Cube\Test\CubeTestCase;
use PHPUnit\Framework\TestCase;

class MathControllerTest extends CubeTestCase
{
    public function testMultiplyRoute() {
        $response = $this->get('/maths/double/2');
        $response->assertOk()->assertJsonContent(4);
    }
}